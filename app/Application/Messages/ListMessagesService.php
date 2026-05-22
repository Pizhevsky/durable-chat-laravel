<?php

namespace App\Application\Messages;

use App\Contracts\ChatQueryRepositoryInterface;
use App\Domain\Shared\DomainRuleException;

final readonly class ListMessagesService
{
    public function __construct(private ChatQueryRepositoryInterface $queries) {}

    /** @return array<int, array<string, mixed>> */
    public function list(string $chatId, string $userId): array
    {
        if (! $this->queries->isActiveMember($chatId, $userId)) {
            throw new DomainRuleException('User is not an active chat member.', 403, 'NOT_CHAT_MEMBER');
        }

        return $this->queries->listMessages($chatId);
    }
}
