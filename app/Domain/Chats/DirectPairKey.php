<?php

namespace App\Domain\Chats;

use App\Domain\Shared\DomainRuleException;

final readonly class DirectPairKey
{
    private function __construct(public string $value) {}

    /** @param array<int, string> $userIds */
    public static function fromUserIds(array $userIds): self
    {
        $uniqueIds = array_values(array_unique(array_filter(
            array_map(fn (string $id): string => trim($id), $userIds),
            fn (string $id): bool => $id !== '',
        )));

        if (count($uniqueIds) !== 2) {
            throw new DomainRuleException(
                'Direct chats must contain exactly two unique participants including the actor.',
                422,
                'INVALID_DIRECT_CHAT',
            );
        }

        sort($uniqueIds, SORT_STRING);

        return new self(implode(':', $uniqueIds));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
