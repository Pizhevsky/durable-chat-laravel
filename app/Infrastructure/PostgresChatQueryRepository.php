<?php

namespace App\Infrastructure;

use App\Contracts\ChatQueryRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class PostgresChatQueryRepository implements ChatQueryRepositoryInterface
{
    public function __construct(
        private PostgresMessageHydrator $messageHydrator,
        private PostgresChatSummaryLoader $chatSummaryLoader,
    ) {}

    public function listUsers(): array
    {
        return DB::table('users')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'role' => $row->role,
                'isOnline' => false,
            ])
            ->all();
    }

    public function listChats(string $userId): array
    {
        $rows = DB::table('chats as c')
            ->join('chat_members as cm', 'cm.chat_id', '=', 'c.id')
            ->where('cm.user_id', $userId)
            ->whereNull('cm.left_at')
            ->orderByDesc('c.created_at')
            ->select([
                'c.id',
                'c.client_chat_id',
                'c.direct_pair_key',
                'c.type',
                'c.title',
                'c.created_by',
                'c.created_at',
                'c.sync_status',
            ])
            ->get();

        $chatIds = $rows->pluck('id')->all();
        $membersByChat = $this->chatSummaryLoader->membersForChats($chatIds);
        $lastMessagesByChat = $this->chatSummaryLoader->lastMessagesForChats($chatIds);
        $unreadByChat = $this->chatSummaryLoader->unreadCountsForChats($chatIds, $userId);

        return $rows->map(function (object $row) use ($userId, $membersByChat, $lastMessagesByChat, $unreadByChat): array {
            $members = $membersByChat[$row->id] ?? [];
            $title = $row->type === 'direct'
                ? $this->directChatTitle($members, $userId)
                : ($row->title ?: 'Group chat');

            return [
                'id' => $row->id,
                'clientChatId' => $row->client_chat_id,
                'directPairKey' => $row->direct_pair_key,
                'type' => $row->type,
                'title' => $title,
                'createdBy' => $row->created_by,
                'createdAt' => $row->created_at,
                'syncStatus' => $row->sync_status,
                'members' => $members,
                'unreadCount' => $unreadByChat[$row->id] ?? 0,
                'lastMessage' => $lastMessagesByChat[$row->id] ?? null,
            ];
        })->all();
    }

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

        return $this->messageHydrator->hydrate($rows);
    }

    public function activeMemberIds(string $chatId): array
    {
        return DB::table('chat_members')
            ->where('chat_id', $chatId)
            ->whereNull('left_at')
            ->pluck('user_id')
            ->all();
    }

    public function usersShareActiveChat(string $firstUserId, string $secondUserId): bool
    {
        if ($firstUserId === $secondUserId) {
            return false;
        }

        return DB::table('chat_members as first_member')
            ->join('chat_members as second_member', 'second_member.chat_id', '=', 'first_member.chat_id')
            ->where('first_member.user_id', $firstUserId)
            ->where('second_member.user_id', $secondUserId)
            ->whereNull('first_member.left_at')
            ->whereNull('second_member.left_at')
            ->exists();
    }

    public function isActiveMember(string $chatId, string $userId): bool
    {
        return DB::table('chat_members')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }

    /** @param array<int, array<string, mixed>> $members */
    private function directChatTitle(array $members, string $currentUserId): string
    {
        $other = collect($members)->firstWhere('userId', '!=', $currentUserId);

        return is_array($other) && isset($other['name']) ? (string) $other['name'] : 'Direct chat';
    }
}
