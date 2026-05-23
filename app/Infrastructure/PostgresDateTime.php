<?php

namespace App\Infrastructure;

use Carbon\CarbonImmutable;

final class PostgresDateTime
{
    public function fromClientValue(string $value): string
    {
        return CarbonImmutable::parse($value)->utc()->toISOString();
    }

    public function toClientIso(mixed $value): string
    {
        return CarbonImmutable::parse((string) $value)->utc()->toISOString();
    }
}
