<?php

namespace App\Application\Sync;

use App\Domain\Shared\DomainRuleException;
use Throwable;

final class SyncConflictMapper
{
    /** @return array<string, mixed> */
    public function fromThrowable(string $eventId, Throwable $error, string $fallbackCode = 'SYNC_ERROR'): array
    {
        $status = $error instanceof DomainRuleException ? $error->statusCode() : 500;
        $code = $error instanceof DomainRuleException ? $error->errorCode() : $fallbackCode;

        return [
            'eventId' => $eventId,
            'code' => $code,
            'message' => $error->getMessage(),
            'status' => $status,
            'category' => $this->categoryFor($code, $status),
            'retryable' => $this->isRetryable($code, $status),
        ];
    }

    private function categoryFor(string $code, int $status): string
    {
        if ($status >= 500) {
            return 'temporary';
        }

        return match ($code) {
            'USER_NOT_FOUND',
            'CHAT_NOT_FOUND',
            'NOT_CHAT_MEMBER',
            'MEMBER_NOT_FOUND' => 'missing_reference',
            'CAUSAL_DEPENDENCY_MISSING',
            'LOGICAL_CLOCK_REGRESSION' => 'causal_ordering',
            'DIRECT_CHAT_EXISTS' => 'duplicate',
            'DIRECT_CHAT_LOCKED',
            'OWNER_CANNOT_LEAVE',
            'NOT_GROUP_CHAT',
            'NOT_GROUP_OWNER' => 'domain_rule',
            default => 'validation',
        };
    }

    private function isRetryable(string $code, int $status): bool
    {
        if ($status >= 500) {
            return true;
        }

        return match ($code) {
            'USER_NOT_FOUND',
            'CHAT_NOT_FOUND',
            'NOT_CHAT_MEMBER',
            'MEMBER_NOT_FOUND',
            'CAUSAL_DEPENDENCY_MISSING' => true,
            default => false,
        };
    }
}
