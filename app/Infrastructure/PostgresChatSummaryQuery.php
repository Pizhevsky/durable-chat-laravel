<?php

namespace App\Infrastructure;

use Illuminate\Support\Facades\DB;

final readonly class PostgresChatSummaryQuery
{
    public function __construct(private PostgresMessageHydrator $hydrator) {}

    /** @param array<int, string> $chatIds @return array<string, array<string, mixed>> */
    public function lastMessagesForChats(array $chatIds): array
    {
        if ($chatIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($chatIds), '?'));
        $rows = collect(DB::select(<<<SQL
            SELECT DISTINCT ON (m.chat_id)
                m.id,
                m.client_message_id,
                m.chat_id,
                m.sender_id,
                u.name as sender_name,
                m.text,
                m.created_at,
                m.sync_status
            FROM messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.chat_id IN ({$placeholders})
            ORDER BY m.chat_id, m.created_at DESC, m.id DESC
        SQL, $chatIds));

        return collect($this->hydrator->hydrate($rows))
            ->keyBy('chatId')
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
