<?php

namespace App\Application\Sync;

use App\Domain\Shared\DomainRuleException;
use Throwable;

final readonly class SyncConflictFactory
{
    public function fromDomainException(string $eventId, DomainRuleException $exception): SyncConflictDto
    {
        return new SyncConflictDto(
            eventId: $eventId,
            code: $exception->errorCode(),
            message: $exception->getMessage(),
            status: $exception->statusCode(),
            category: $this->categoryFor($exception->errorCode()),
            retryable: $this->isRetryable($exception->errorCode()),
        );
    }

    public function fromThrowable(string $eventId, Throwable $exception): SyncConflictDto
    {
        return new SyncConflictDto(
            eventId: $eventId,
            code: 'SYNC_STORAGE_ERROR',
            message: 'Central could not persist or project the event.',
            status: 500,
            category: 'storage',
            retryable: true,
        );
    }

    private function categoryFor(string $code): string
    {
        return match ($code) {
            'INVALID_EVENT', 'INVALID_EVENT_TYPE', 'INVALID_DIRECT_CHAT' => 'validation',
            'CAUSAL_CLOCK_REGRESSION', 'CAUSAL_DEPENDENCY_MISSING' => 'causal_ordering',
            'DIRECT_CHAT_EXISTS', 'NOT_CHAT_MEMBER', 'NOT_GROUP_OWNER', 'NOT_GROUP_CHAT',
            'DIRECT_CHAT_LOCKED', 'OWNER_CANNOT_LEAVE', 'MEMBER_NOT_FOUND' => 'domain_rule',
            'USER_NOT_FOUND', 'CHAT_NOT_FOUND' => 'missing_reference',
            default => 'domain_rule',
        };
    }

    private function isRetryable(string $code): bool
    {
        return match ($this->categoryFor($code)) {
            'causal_ordering', 'missing_reference' => true,
            default => false,
        };
    }
}
