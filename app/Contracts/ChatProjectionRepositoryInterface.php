<?php

namespace App\Contracts;

use App\Domain\Events\ChatEventDto;

interface ChatProjectionRepositoryInterface
{
    public function userExists(string $userId): bool;

    public function directChatIdByPairKey(string $directPairKey): ?string;

    /** @return array{id: string, type: string}|null */
    public function findChat(string $chatId): ?array;

    public function isActiveMember(string $chatId, string $userId): bool;

    public function isGroupOwner(string $chatId, string $userId): bool;

    public function messageExists(string $messageId): bool;

    /** @param array<int, string> $memberIds */
    public function createChat(ChatEventDto $event, array $memberIds, ?string $directPairKey): void;

    public function addMember(string $chatId, string $memberId, string $joinedAt, bool $isOwner): void;

    public function removeMember(string $chatId, string $memberId, string $leftAt): bool;

    public function createMessage(ChatEventDto $event): void;

    public function markMessageRead(string $messageId, string $userId, string $readAt): void;
}
