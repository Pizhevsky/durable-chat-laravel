<?php

namespace App\Infrastructure;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Domain\Events\ChatEventDto;
use Illuminate\Support\Facades\DB;

final readonly class PostgresChatProjectionRepository implements ChatProjectionRepositoryInterface
{
    public function __construct(
        private PostgresDateTime $dateTime,
        private PostgresProjectionMapper $mapper,
    ) {}

    public function userExists(string $userId): bool
    {
        return DB::table('users')->where('id', $userId)->exists();
    }

    public function directChatIdByPairKey(string $directPairKey): ?string
    {
        $id = DB::table('chats')->where('direct_pair_key', $directPairKey)->value('id');

        return is_string($id) ? $id : null;
    }

    public function findChat(string $chatId): ?array
    {
        $row = DB::table('chats')->where('id', $chatId)->select(['id', 'type'])->first();

        return $row === null ? null : ['id' => $row->id, 'type' => $row->type];
    }

    public function isActiveMember(string $chatId, string $userId): bool
    {
        return DB::table('chat_members')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }

    public function isGroupOwner(string $chatId, string $userId): bool
    {
        return DB::table('chat_members')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->where('is_owner', true)
            ->exists();
    }

    public function messageExists(string $messageId): bool
    {
        return DB::table('messages')->where('id', $messageId)->exists();
    }

    public function createChat(ChatEventDto $event, array $memberIds, ?string $directPairKey): void
    {
        DB::table('chats')->insert($this->mapper->chatRow($event, $directPairKey));

        foreach ($memberIds as $memberId) {
            $this->addMember(
                chatId: $event->payload['chatId'],
                memberId: $memberId,
                joinedAt: $event->createdAt,
                isOwner: $memberId === $event->actorUserId,
            );
        }
    }

    public function addMember(string $chatId, string $memberId, string $joinedAt, bool $isOwner): void
    {
        DB::table('chat_members')->upsert(
            [$this->mapper->memberRow($chatId, $memberId, $joinedAt, $isOwner)],
            ['chat_id', 'user_id'],
            ['joined_at', 'left_at', 'is_owner'],
        );
    }

    public function removeMember(string $chatId, string $memberId, string $leftAt): bool
    {
        return DB::table('chat_members')
            ->where('chat_id', $chatId)
            ->where('user_id', $memberId)
            ->where('is_owner', false)
            ->whereNull('left_at')
            ->update(['left_at' => $this->dateTime->fromClientValue($leftAt)]) > 0;
    }

    public function createMessage(ChatEventDto $event): void
    {
        DB::table('messages')->insert($this->mapper->messageRow($event));
    }

    public function markMessageRead(string $messageId, string $userId, string $readAt): void
    {
        DB::table('message_reads')->upsert(
            [$this->mapper->messageReadRow($messageId, $userId, $readAt)],
            ['message_id', 'user_id'],
            ['read_at'],
        );
    }
}
