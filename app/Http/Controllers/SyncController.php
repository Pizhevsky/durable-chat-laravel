<?php

namespace App\Http\Controllers;

use App\Application\Sync\SyncEventsService;
use App\Contracts\EventRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class SyncController
{
    private const DEFAULT_PULL_LIMIT = 100;

    private const MAX_PULL_LIMIT = 500;

    public function __construct(
        private SyncEventsService $syncEvents,
        private EventRepositoryInterface $events,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $events = $request->input('events', []);
        $result = $this->syncEvents->sync(is_array($events) ? $events : []);

        return response()->json($result->toResponseArray());
    }

    public function index(Request $request): JsonResponse
    {
        $since = max(0, (int) $request->query('since', 0));
        $limit = min(self::MAX_PULL_LIMIT, max(1, (int) $request->query('limit', self::DEFAULT_PULL_LIMIT)));
        $rows = $this->events->findSinceWithSequences($since, $limit);
        $latestReturnedSequence = $rows === [] ? $since : (int) $rows[array_key_last($rows)]['sequence'];
        $currentSequence = $this->events->currentSequence();
        $centralNodeId = config('durable-chat.central_node_id');

        return response()->json([
            'centralNodeId' => $centralNodeId,
            'nodeRole' => 'central',
            'nodeId' => $centralNodeId,
            'latestSequence' => $latestReturnedSequence,
            'currentSequence' => $currentSequence,
            'hasMore' => $latestReturnedSequence < $currentSequence,
            'events' => array_map(
                fn (array $row): array => $row['event']->toArray(),
                $rows,
            ),
        ]);
    }
}
