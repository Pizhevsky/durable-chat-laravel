<?php

namespace App\Application\Recovery;

use App\Contracts\EventRepositoryInterface;
use App\Domain\Events\ChatEventDto;

final readonly class ExportRecoveryDumpService
{
    public function __construct(
        private EventRepositoryInterface $events,
        private RecoveryChecksum $checksum,
    ) {}

    /** @return array<string, mixed> */
    public function export(string $userId, string $deviceId): array
    {
        $events = array_map(
            fn (ChatEventDto $event): array => $event->toArray(),
            $this->events->all(),
        );

        return [
            'format' => config('durable-chat.recovery_format'),
            'exportedAt' => now()->toISOString(),
            'exportedBy' => $userId,
            'deviceId' => $deviceId,
            'events' => $events,
            'checksum' => $this->checksum->forEvents($events),
            'note' => 'Laravel central recovery export. Browser IndexedDB exports remain available from the client UI.',
        ];
    }
}
