<?php

namespace App\Http\Controllers;

use App\Application\Events\ApplyChatEventService;
use App\Domain\Events\ChatEventDto;
use App\Domain\Shared\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class EventController
{
    public function __construct(private ApplyChatEventService $applyEvent) {}

    public function store(Request $request): JsonResponse
    {
        $userId = (string) $request->header('x-demo-user-id', '');
        if ($userId === '') {
            throw new DomainRuleException('Missing x-demo-user-id header for demo auth event publishing.', 401, 'MISSING_DEMO_USER');
        }

        $payload = $request->json()->all();
        $event = ChatEventDto::fromArray($payload)->withActor($userId);
        $result = $this->applyEvent->apply($event);

        return response()->json($result->event->toArray(), $result->inserted ? 201 : 200);
    }
}
