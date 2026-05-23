<?php

namespace App\Application\Sync;

use App\Domain\Events\ChatEventDto;

final readonly class SyncEventsResultDto
{
    /**
     * @param  array<int, string>  $accepted
     * @param  array<int, string>  $duplicates
     * @param  array<int, array<string, mixed>>  $conflicts
     * @param  array<int, ChatEventDto>  $serverEvents
     */
    public function __construct(
        public array $accepted,
        public array $duplicates,
        public array $conflicts,
        public array $serverEvents,
        public bool $dryRun = false,
    ) {}

    /** @return array<string, mixed> */
    public function toResponseArray(): array
    {
        $centralNodeId = config('durable-chat.central_node_id');

        return [
            'accepted' => $this->accepted,
            'duplicates' => $this->duplicates,
            'conflicts' => $this->conflicts,
            'serverEvents' => array_map(
                fn (ChatEventDto $event): array => $event->toArray(),
                $this->serverEvents,
            ),
            'centralNodeId' => $centralNodeId,
            'nodeRole' => 'central',
            'nodeId' => $centralNodeId,
            'dryRun' => $this->dryRun,
        ];
    }
}
