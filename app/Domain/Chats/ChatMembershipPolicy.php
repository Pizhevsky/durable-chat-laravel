<?php

namespace App\Domain\Chats;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Domain\Shared\DomainRuleException;

final readonly class ChatMembershipPolicy
{
    public function __construct(private ChatProjectionRepositoryInterface $projection) {}

    public function assertActiveMember(string $chatId, string $userId): void
    {
        if (! $this->projection->isActiveMember($chatId, $userId)) {
            throw new DomainRuleException('User is not an active chat member.', 403, 'NOT_CHAT_MEMBER');
        }
    }
}
