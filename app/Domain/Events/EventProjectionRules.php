<?php

namespace App\Domain\Events;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Domain\Chats\DirectChatKeyFactory;
use App\Domain\Shared\DomainRuleException;

final readonly class EventProjectionRules
{
    public function __construct(
        private ChatProjectionRepositoryInterface $projection,
        private DirectChatKeyFactory $directChatKeyFactory,
    ) {}

    /** @return array<int, string> */
    public function uniqueChatMemberIds(ChatEventDto $event): array
    {
        return array_values(array_unique([$event->actorUserId, ...$event->payload['memberIds']]));
    }

    /** @param array<int, string> $memberIds */
    public function directPairKeyFor(array $memberIds): string
    {
        return $this->directChatKeyFactory->make($memberIds);
    }

    public function assertDirectChatDoesNotExist(?string $directPairKey): void
    {
        if ($directPairKey !== null && $this->projection->directChatIdByPairKey($directPairKey) !== null) {
            throw new DomainRuleException('A direct chat for this pair already exists.', 409, 'DIRECT_CHAT_EXISTS');
        }
    }

    /** @param array<int, string> $userIds */
    public function assertUsersExist(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->assertUserExists($userId);
        }
    }

    public function assertUserExists(string $userId): void
    {
        if (! $this->projection->userExists($userId)) {
            throw new DomainRuleException("Unknown user: {$userId}", 404, 'USER_NOT_FOUND');
        }
    }

    public function assertActiveMember(string $chatId, string $userId): void
    {
        if (! $this->projection->isActiveMember($chatId, $userId)) {
            throw new DomainRuleException('User is not an active chat member.', 403, 'NOT_CHAT_MEMBER');
        }
    }

    /** @return array{id: string, type: string} */
    public function assertGroupOwner(string $chatId, string $userId): array
    {
        $chat = $this->projection->findChat($chatId);
        if ($chat === null) {
            throw new DomainRuleException('Chat not found.', 404, 'CHAT_NOT_FOUND');
        }

        if ($chat['type'] !== 'group') {
            throw new DomainRuleException('Membership changes are only supported for group chats.', 422, 'NOT_GROUP_CHAT');
        }

        if (! $this->projection->isGroupOwner($chatId, $userId)) {
            throw new DomainRuleException('Only the group owner can change members.', 403, 'NOT_GROUP_OWNER');
        }

        return $chat;
    }
}
