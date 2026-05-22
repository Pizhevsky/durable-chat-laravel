<?php

namespace App\Application\Recovery;

use App\Contracts\EventRepositoryInterface;
use App\Domain\Events\ChatEventDto;

final readonly class ExportRecoveryDumpService
{
    public function __construct(private EventRepositoryInterface $events) {}

    /** @return array<string, mixed> */
    public function export(string $userId, string $deviceId): array
    {
        $events = array_map(
            fn (ChatEventDto $event): array => $event->toArray(),
            $this->events->all((int) config('durable-chat.recovery_export_limit')),
        );

        return [
            'format' => config('durable-chat.recovery_format'),
            'exportedAt' => now()->toISOString(),
            'exportedBy' => $userId,
            'deviceId' => $deviceId,
            'latestSequence' => $this->events->currentSequence(),
            'eventCount' => count($events),
            'exportLimit' => (int) config('durable-chat.recovery_export_limit'),
            'truncated' => $this->events->count() > count($events),
            'checksum' => hash('sha256', json_encode($events, JSON_THROW_ON_ERROR)),
            'orderingPolicy' => 'central-sequence-ascending',
            'replayGuarantee' => 'imports are idempotent by eventId and reuse the same sync conflict taxonomy as helper sync',
            'events' => $events,
            'note' => 'Laravel central recovery export. Browser IndexedDB exports remain available from the client UI.',
        ];
    }
}
