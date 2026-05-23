<?php

namespace App\Infrastructure;

use App\Contracts\MessageQueryRepositoryInterface;
use Illuminate\Support\Facades\DB;

final readonly class PostgresMessageQueryRepository implements MessageQueryRepositoryInterface
{
    public function __construct(
        private PostgresChatMemberQuery $members,
        private PostgresMessageHydrator $hydrator,
    ) {}

    public function listMessages(string $chatId): array
    {
        $rows = DB::table('messages as m')
            ->join('users as u', 'u.id', '=', 'm.sender_id')
            ->where('m.chat_id', $chatId)
            ->orderBy('m.created_at')
            ->select([
                'm.id',
                'm.client_message_id',
                'm.chat_id',
                'm.sender_id',
                'u.name as sender_name',
                'm.text',
                'm.created_at',
                'm.sync_status',
            ])
            ->get();

        return $this->hydrator->hydrate($rows);
    }

    public function isActiveMember(string $chatId, string $userId): bool
    {
        return $this->members->isActiveMember($chatId, $userId);
    }
}
