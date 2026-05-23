<?php

namespace App\Contracts;

interface MessageQueryRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function listMessages(string $chatId): array;

    public function isActiveMember(string $chatId, string $userId): bool;
}
