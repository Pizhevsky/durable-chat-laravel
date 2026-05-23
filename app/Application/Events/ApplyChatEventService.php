<?php

namespace App\Application\Events;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Contracts\EventRepositoryInterface;
use App\Domain\Chats\DirectChatKeyFactory;
use App\Domain\Events\CausalOrderingPolicy;
use App\Domain\Events\ChatEventDto;
use App\Domain\Events\EventProjector;
use App\Domain\Events\EventSyncStatus;
use App\Domain\Events\EventType;
use App\Domain\Events\EventValidator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class ApplyChatEventService
{
    public function __construct(
        private EventRepositoryInterface $events,
        private EventValidator $validator,
        private EventProjector $projector,
        private CausalOrderingPolicy $causalOrderingPolicy,
        private DirectChatKeyFactory $directChatKeyFactory,
        private ChatProjectionRepositoryInterface $chatProjection,
    ) {}

    public function apply(ChatEventDto $incomingEvent): ApplyEventResultDto
    {
        $this->validator->validate($incomingEvent);

        $event = $incomingEvent->withSyncStatus(EventSyncStatus::forCentralStorage());

        try {
            return DB::transaction(function () use ($event): ApplyEventResultDto {
                $existing = $this->events->findByEventId($event->eventId);
                if ($existing !== null) {
                    return new ApplyEventResultDto($existing, false);
                }

                $existingDirectChatEvent = $this->findExistingDirectChatCreatedEvent($event);
                if ($existingDirectChatEvent !== null) {
                    return new ApplyEventResultDto($existingDirectChatEvent, false);
                }

                $this->causalOrderingPolicy->assertSatisfied($event);
                $this->events->store($event);
                $this->projector->project($event);

                return new ApplyEventResultDto($event, true);
            });
        } catch (QueryException $exception) {
            $existing = $this->events->findByEventId($event->eventId)
                ?? $this->findExistingDirectChatCreatedEvent($event);

            if ($existing !== null && $this->isUniqueConstraintViolation($exception)) {
                return new ApplyEventResultDto($existing, false);
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function findExistingDirectChatCreatedEvent(ChatEventDto $event): ?ChatEventDto
    {
        if ($event->type !== EventType::ChatCreated) {
            return null;
        }

        if (($event->payload['type'] ?? null) !== 'direct') {
            return null;
        }

        $memberIds = array_values(array_unique([$event->actorUserId, ...($event->payload['memberIds'] ?? [])]));
        if (count($memberIds) !== 2) {
            return null;
        }

        $directPairKey = $this->directChatKeyFactory->make($memberIds);
        $existingChatId = $this->chatProjection->directChatIdByPairKey($directPairKey);

        return $existingChatId === null ? null : $this->events->findChatCreatedEvent($existingChatId);
    }
}
