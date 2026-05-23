<?php

namespace App\Infrastructure;

use App\Contracts\UserQueryRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class PostgresUserQueryRepository implements UserQueryRepositoryInterface
{
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
}
