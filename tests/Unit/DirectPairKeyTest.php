<?php

namespace Tests\Unit;

use App\Domain\Chats\DirectPairKey;
use App\Domain\Shared\DomainRuleException;
use PHPUnit\Framework\TestCase;

final class DirectPairKeyTest extends TestCase
{
    public function test_it_builds_a_canonical_pair_key_independent_of_order(): void
    {
        self::assertSame('u-anna:u-denis', DirectPairKey::fromUserIds(['u-denis', 'u-anna'])->value);
        self::assertSame('u-anna:u-denis', DirectPairKey::fromUserIds(['u-anna', 'u-denis'])->value);
    }

    public function test_it_rejects_direct_chats_without_two_unique_members(): void
    {
        $this->expectException(DomainRuleException::class);

        DirectPairKey::fromUserIds(['u-denis', 'u-denis']);
    }
}
