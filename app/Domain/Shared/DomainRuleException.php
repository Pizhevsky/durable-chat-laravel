<?php

namespace App\Domain\Shared;

use RuntimeException;

final class DomainRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 422,
        private readonly string $errorCode = 'DOMAIN_RULE_FAILED',
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
