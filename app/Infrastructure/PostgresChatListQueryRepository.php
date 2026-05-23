<?php

namespace App\Infrastructure;

use App\Contracts\ChatListQueryRepositoryInterface;
use Illuminate\Support\Facades\DB;

final readonly class PostgresChatListQueryRepository implements ChatListQueryRepositoryInterface
{
    public function __construct(
        private PostgresChatMemberQuery $members,
        private PostgresChatSummaryQuery $summary,
    ) {}

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
        $membersByChat = $this->members->membersForChats($chatIds);
        $lastMessagesByChat = $this->summary->lastMessagesForChats($chatIds);
        $unreadByChat = $this->summary->unreadCountsForChats($chatIds, $userId);

        return $rows->map(
            function (object $row)
            use ($userId, $membersByChat, $lastMessagesByChat, $unreadByChat): array {
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
            }
        )->all();
    }

    /** @param array<int, array<string, mixed>> $members */
    private function directChatTitle(array $members, string $currentUserId): string
    {
        $other = collect($members)->firstWhere('userId', '!=', $currentUserId);

        return is_array($other) && isset($other['name']) ? (string) $other['name'] : 'Direct chat';
    }
}
