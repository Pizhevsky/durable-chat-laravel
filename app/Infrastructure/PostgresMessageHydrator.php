<?php

namespace App\Infrastructure;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PostgresMessageHydrator
{
    /** @param Collection<int, object> $rows @return array<int, array<string, mixed>> */
    public function hydrate(Collection $rows): array
    {
        $messageIds = $rows->pluck('id')->all();
        $readBy = $this->readByForMessages($messageIds);

        return $rows->map(fn (object $row): array => [
            'id' => $row->id,
            'clientMessageId' => $row->client_message_id,
            'chatId' => $row->chat_id,
            'senderId' => $row->sender_id,
            'senderName' => $row->sender_name,
            'text' => $row->text,
            'createdAt' => $row->created_at,
            'syncStatus' => $row->sync_status,
            'readBy' => $readBy[$row->id] ?? [],
        ])->all();
    }

    /** @param array<int, string> $messageIds @return array<string, array<int, string>> */
    private function readByForMessages(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        return DB::table('message_reads')
            ->whereIn('message_id', $messageIds)
            ->orderBy('read_at')
            ->get(['message_id', 'user_id'])
            ->groupBy('message_id')
            ->map(fn (Collection $rows): array => $rows->pluck('user_id')->all())
            ->all();
    }
}
