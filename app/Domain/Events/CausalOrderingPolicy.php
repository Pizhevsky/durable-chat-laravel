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

    public function assertAcceptable(ChatEventDto $event): void
    {
        $latestLogicalClock = $this->events->latestLogicalClockForOriginDevice($event->originDeviceId);
        if ($latestLogicalClock !== null && $event->logicalClock <= $latestLogicalClock) {
            throw new DomainRuleException(
                'Event logicalClock must advance for the origin device.',
                409,
                'CAUSAL_CLOCK_REGRESSION',
            );
        }

        if ($event->type === EventType::ChatCreated) {
            return;
        }

        if ($this->projection->findChat($event->chatId) === null) {
            throw new DomainRuleException(
                'Event depends on a chat that has not been accepted by central yet.',
                409,
                'CAUSAL_DEPENDENCY_MISSING',
            );
        }

        if ($event->type === EventType::MessageRead && ! $this->projection->messageExists((string) ($event->payload['messageId'] ?? ''))) {
            throw new DomainRuleException(
                'Read receipt depends on a message that has not been accepted by central yet.',
                409,
                'CAUSAL_DEPENDENCY_MISSING',
            );
        }
    }
}
