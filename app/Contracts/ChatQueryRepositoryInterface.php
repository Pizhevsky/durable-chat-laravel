<?php

namespace App\Contracts;

interface ChatQueryRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function listUsers(): array;

    /** @return array<int, array<string, mixed>> */
    public function listChats(string $userId): array;

    /** @return array<int, array<string, mixed>> */
    public function listMessages(string $chatId): array;

    public function isActiveMember(string $chatId, string $userId): bool;

    /** @return array<int, string> */
    public function activeMemberIds(string $chatId): array;

    public function usersShareActiveChat(string $firstUserId, string $secondUserId): bool;
}
