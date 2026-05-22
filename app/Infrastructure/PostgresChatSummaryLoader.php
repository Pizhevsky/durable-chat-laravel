<?php

namespace App\Infrastructure;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PostgresChatSummaryLoader
{
    public function __construct(private PostgresMessageHydrator $messageHydrator) {}

    /** @param array<int, string> $chatIds @return array<string, array<int, array<string, mixed>>> */
    public function membersForChats(array $chatIds): array
    {
        if ($chatIds === []) {
            return [];
        }

        return DB::table('chat_members as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->whereIn('cm.chat_id', $chatIds)
            ->whereNull('cm.left_at')
            ->orderBy('cm.chat_id')
            ->orderByDesc('cm.is_owner')
            ->orderBy('u.name')
            ->select(['cm.chat_id', 'cm.user_id', 'u.name', 'cm.joined_at', 'cm.left_at', 'cm.is_owner'])
            ->get()
            ->groupBy('chat_id')
            ->map(fn (Collection $rows): array => $rows->map(fn (object $row): array => [
                'userId' => $row->user_id,
                'name' => $row->name,
                'joinedAt' => $row->joined_at,
                'leftAt' => $row->left_at,
                'isOwner' => (bool) $row->is_owner,
            ])->all())
            ->all();
    }

    /** @param array<int, string> $chatIds @return array<string, array<string, mixed>> */
    public function lastMessagesForChats(array $chatIds): array
    {
        if ($chatIds === []) {
            return [];
        }

        return DB::table('messages as m')
            ->join('users as u', 'u.id', '=', 'm.sender_id')
            ->whereIn('m.chat_id', $chatIds)
            ->selectRaw('DISTINCT ON (m.chat_id) m.id, m.client_message_id, m.chat_id, m.sender_id, u.name as sender_name, m.text, m.created_at, m.sync_status')
            ->orderBy('m.chat_id')
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id')
            ->get()
            ->groupBy('chat_id')
            ->map(fn (Collection $messages): array => $this->messageHydrator->hydrate(collect([$messages->first()]))[0])
            ->all();
    }

    /** @param array<int, string> $chatIds @return array<string, int> */
    public function unreadCountsForChats(array $chatIds, string $currentUserId): array
    {
        if ($chatIds === []) {
            return [];
        }

        return DB::table('messages as m')
            ->leftJoin('message_reads as mr', function ($join) use ($currentUserId): void {
                $join->on('mr.message_id', '=', 'm.id')->where('mr.user_id', '=', $currentUserId);
            })
            ->whereIn('m.chat_id', $chatIds)
            ->where('m.sender_id', '!=', $currentUserId)
            ->whereNull('mr.message_id')
            ->groupBy('m.chat_id')
            ->selectRaw('m.chat_id, COUNT(*) as count')
            ->pluck('count', 'chat_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }
}
