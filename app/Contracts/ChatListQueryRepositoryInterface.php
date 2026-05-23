<?php

namespace App\Contracts;

interface ChatListQueryRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function listChats(string $userId): array;
}
