<?php

namespace App\Application\Sync;

use App\Domain\Events\ChatEventDto;

final readonly class SyncEventsResultDto
{
    /**
     * @param  array<int, string>  $accepted
     * @param  array<int, string>  $duplicates
     * @param  array<int, SyncConflictDto>  $conflicts
     * @param  array<int, ChatEventDto>  $serverEvents
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $accepted,
        public array $duplicates,
        public array $conflicts,
        public array $serverEvents,
        public array $meta,
    ) {}

    /** @return array<string, mixed> */
    public function toResponseArray(): array
    {
        $conflictIds = array_map(
            fn (SyncConflictDto $conflict): string => $conflict->eventId,
            $this->conflicts,
        );

        return [
            'accepted' => $this->accepted,
            'duplicates' => $this->duplicates,
            'conflictIds' => $conflictIds,
            'conflicts' => array_map(
                fn (SyncConflictDto $conflict): array => $conflict->toArray(),
                $this->conflicts,
            ),
            'serverEvents' => array_map(
                fn (ChatEventDto $event): array => $event->toArray(),
                $this->serverEvents,
            ),
            'centralNodeId' => config('durable-chat.central_node_id'),
            'meta' => $this->meta,
        ];
    }
}
