<?php

namespace App\Application\Sync;

use App\Application\Events\ApplyChatEventService;
use App\Domain\Events\ChatEventDto;
use App\Domain\Shared\DomainRuleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class SyncEventsService
{
    public function __construct(
        private ApplyChatEventService $applyEvent,
        private SyncConflictFactory $conflictFactory,
    ) {}

    /** @param array<int, array<string, mixed>> $eventRows */
    public function sync(array $eventRows, string $sourceNodeId = 'unknown'): SyncEventsResultDto
    {
        $syncAttemptId = (string) Str::uuid();
        $startedAt = now()->toISOString();
        $accepted = [];
        $duplicates = [];
        $conflicts = [];
        $serverEvents = [];

        foreach ($eventRows as $eventRow) {
            $eventId = is_array($eventRow) && is_string($eventRow['eventId'] ?? null)
                ? $eventRow['eventId']
                : 'unknown';

            try {
                $event = ChatEventDto::fromArray($eventRow);
                $result = $this->applyEvent->apply($event);

                $serverEvents[] = $result->event;
                if ($result->inserted) {
                    $accepted[] = $event->eventId;
                } else {
                    $duplicates[] = $event->eventId;
                }
            } catch (DomainRuleException $exception) {
                $conflict = $this->conflictFactory->fromDomainException($eventId, $exception);
                $conflicts[] = $conflict;
                Log::warning('durable-chat.sync.event_conflict', [
                    'syncAttemptId' => $syncAttemptId,
                    'sourceNodeId' => $sourceNodeId,
                    'eventId' => $eventId,
                    'code' => $conflict->code,
                    'category' => $conflict->category,
                ]);
            } catch (Throwable $exception) {
                $conflict = $this->conflictFactory->fromThrowable($eventId, $exception);
                $conflicts[] = $conflict;
                Log::error('durable-chat.sync.event_error', [
                    'syncAttemptId' => $syncAttemptId,
                    'sourceNodeId' => $sourceNodeId,
                    'eventId' => $eventId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $meta = [
            'syncAttemptId' => $syncAttemptId,
            'sourceNodeId' => $sourceNodeId,
            'receivedAt' => $startedAt,
            'completedAt' => now()->toISOString(),
            'orderingPolicy' => 'batch-order-with-per-device-logical-clock',
            'replayGuarantee' => 'eventId and direct-chat uniqueness are idempotent; accepted events are returned in central sequence order by pull sync',
            'counts' => [
                'received' => count($eventRows),
                'accepted' => count($accepted),
                'duplicates' => count($duplicates),
                'conflicts' => count($conflicts),
                'serverEvents' => count($serverEvents),
            ],
        ];

        Log::info('durable-chat.sync.completed', $meta);

        return new SyncEventsResultDto($accepted, $duplicates, $conflicts, $serverEvents, $meta);
    }
}
