<?php

namespace App\Domain\Events;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Contracts\EventRepositoryInterface;
use App\Domain\Shared\DomainRuleException;

final readonly class CausalOrderingPolicy
{
    public function __construct(
        private EventRepositoryInterface $events,
        private ChatProjectionRepositoryInterface $projection,
    ) {}

    public function assertSatisfied(ChatEventDto $event): void
    {
        $this->assertLogicalClockAdvances($event);

        match ($event->type) {
            EventType::ChatCreated => null,
            EventType::MemberAdded,
            EventType::MemberRemoved,
            EventType::MessageCreated => $this->assertChatExists($event->payload['chatId']),
            EventType::MessageRead => $this->assertMessageReadDependencies($event),
        };
    }

    private function assertLogicalClockAdvances(ChatEventDto $event): void
    {
        $maxClock = $this->events->maxLogicalClockForDevice($event->originDeviceId);
        if ($maxClock !== null && $event->logicalClock <= $maxClock) {
            throw new DomainRuleException(
                'Event logical clock is not newer than the latest accepted event from this device.',
                409,
                'LOGICAL_CLOCK_REGRESSION',
            );
        }
    }

    private function assertChatExists(string $chatId): void
    {
        if ($this->projection->findChat($chatId) === null) {
            throw new DomainRuleException(
                'Event depends on a chat that has not been accepted by central yet.',
                409,
                'CAUSAL_DEPENDENCY_MISSING',
            );
        }
    }

    private function assertMessageReadDependencies(ChatEventDto $event): void
    {
        $this->assertChatExists($event->payload['chatId']);

        if (! $this->projection->messageExists($event->payload['messageId'])) {
            throw new DomainRuleException(
                'Read receipt depends on a message that has not been accepted by central yet.',
                409,
                'CAUSAL_DEPENDENCY_MISSING',
            );
        }
    }
}
