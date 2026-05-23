<?php

namespace App\Application\Sync;

use App\Application\Events\ApplyChatEventService;
use App\Domain\Events\ChatEventDto;
use App\Domain\Events\EventType;
use Throwable;

final readonly class SyncEventsService
{
    public function __construct(
        private ApplyChatEventService $applyEvent,
        private SyncConflictMapper $conflictMapper,
    ) {}

    /** @param array<int, array<string, mixed>> $eventRows */
    public function sync(array $eventRows): SyncEventsResultDto
    {
        $accepted = [];
        $duplicates = [];
        $conflicts = [];
        $serverEvents = [];
        $chatIdAliases = [];

        foreach ($eventRows as $eventRow) {
            $eventId = is_array($eventRow) && is_string($eventRow['eventId'] ?? null)
                ? $eventRow['eventId']
                : 'unknown';

            try {
                $event = ChatEventDto::fromArray($this->applyChatIdAliases($eventRow, $chatIdAliases));
                $result = $this->applyEvent->apply($event);

                $serverEvents[] = $result->event;
                if ($result->inserted) {
                    $accepted[] = $event->eventId;
                } else {
                    $duplicates[] = $event->eventId;
                }

                if ($this->isDirectChatCreated($event) && $result->event->chatId !== $event->chatId) {
                    $chatIdAliases[$event->chatId] = $result->event->chatId;
                }
            } catch (Throwable $error) {
                $conflicts[] = $this->conflictMapper->fromThrowable($eventId, $error);
            }
        }

        return new SyncEventsResultDto($accepted, $duplicates, $conflicts, $serverEvents);
    }

    /**
     * @param  array<string, mixed>  $eventRow
     * @param  array<string, string>  $chatIdAliases
     * @return array<string, mixed>
     */
    private function applyChatIdAliases(array $eventRow, array $chatIdAliases): array
    {
        $chatId = $eventRow['chatId'] ?? null;
        if (! is_string($chatId) || ! isset($chatIdAliases[$chatId])) {
            return $eventRow;
        }

        $canonicalChatId = $chatIdAliases[$chatId];
        $eventRow['chatId'] = $canonicalChatId;

        if (isset($eventRow['payload']) && is_array($eventRow['payload'])) {
            $eventRow['payload']['chatId'] = $canonicalChatId;
        }

        return $eventRow;
    }

    private function isDirectChatCreated(ChatEventDto $event): bool
    {
        return $event->type === EventType::ChatCreated
            && ($event->payload['type'] ?? null) === 'direct';
    }
}
