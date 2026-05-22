<?php

namespace Tests\Unit;

use App\Domain\Chats\DirectChatKeyFactory;
use App\Domain\Shared\DomainRuleException;
use PHPUnit\Framework\TestCase;

final class DirectChatKeyFactoryTest extends TestCase
{
    public function test_it_builds_a_canonical_pair_key_independent_of_order(): void
    {
        $factory = new DirectChatKeyFactory;

        self::assertSame('u-anna:u-denis', $factory->make(['u-denis', 'u-anna']));
        self::assertSame('u-anna:u-denis', $factory->make(['u-anna', 'u-denis']));
    }

    public function test_it_rejects_direct_chats_without_two_unique_members(): void
    {
        $this->expectException(DomainRuleException::class);

        (new DirectChatKeyFactory)->make(['u-denis', 'u-denis']);
    }
}
