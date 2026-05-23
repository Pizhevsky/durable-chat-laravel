<?php

namespace App\Infrastructure;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PostgresChatMemberQuery
{
    public function isActiveMember(string $chatId, string $userId): bool
    {
        return DB::table('chat_members')
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->whereNull('left_at')
            ->exists();
    }

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
}
