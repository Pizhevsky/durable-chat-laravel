<?php

namespace App\Domain\Chats;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Domain\Shared\DomainRuleException;

final readonly class DirectChatPolicy
{
    public function __construct(private ChatProjectionRepositoryInterface $projection) {}

    public function assertPairKeyDoesNotExist(string $directPairKey): void
    {
        if ($this->projection->directChatIdByPairKey($directPairKey) !== null) {
            throw new DomainRuleException('A direct chat for this pair already exists.', 409, 'DIRECT_CHAT_EXISTS');
        }
    }
}
