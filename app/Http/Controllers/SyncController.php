<?php

namespace App\Http\Controllers;

use App\Application\Sync\SyncEventsService;
use App\Contracts\EventRepositoryInterface;
use App\Domain\Events\ChatEventDto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class SyncController
{
    public function __construct(
        private SyncEventsService $syncEvents,
        private EventRepositoryInterface $events,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $events = $request->input('events', []);
        $sourceNodeId = (string) $request->input('sourceNodeId', 'unknown');
        $result = $this->syncEvents->sync(is_array($events) ? $events : [], $sourceNodeId);

        return response()->json($result->toResponseArray());
    }

    public function index(Request $request): JsonResponse
    {
        $since = max(0, (int) $request->query('since', 0));
        $limit = min(
            max(1, (int) $request->query('limit', config('durable-chat.sync_pull_limit'))),
            (int) config('durable-chat.max_sync_pull_limit'),
        );

        return response()->json([
            'centralNodeId' => config('durable-chat.central_node_id'),
            'latestSequence' => $this->events->currentSequence(),
            'limit' => $limit,
            'events' => array_map(
                fn (ChatEventDto $event): array => $event->toArray(),
                $this->events->findSince($since, $limit),
            ),
        ]);
    }
}
