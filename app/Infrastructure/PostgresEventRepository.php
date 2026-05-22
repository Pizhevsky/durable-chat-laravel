<?php

namespace App\Infrastructure;

use App\Contracts\EventRepositoryInterface;
use App\Domain\Events\ChatEventDto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;

final class PostgresEventRepository implements EventRepositoryInterface
{
    public function exists(string $eventId): bool
    {
        return DB::table('events')->where('event_id', $eventId)->exists();
    }

    public function store(ChatEventDto $event): void
    {
        DB::table('events')->insert([
            'event_id' => $event->eventId,
            'origin_node_id' => $event->originNodeId,
            'origin_device_id' => $event->originDeviceId,
            'actor_user_id' => $event->actorUserId,
            'chat_id' => $event->chatId,
            'type' => $event->type->value,
            'payload_json' => json_encode($event->payload, JSON_THROW_ON_ERROR),
            'created_at' => CarbonImmutable::parse($event->createdAt)->utc()->toISOString(),
            'logical_clock' => $event->logicalClock,
            'sync_status' => $event->syncStatus->value,
        ]);
    }

    public function findByEventId(string $eventId): ?ChatEventDto
    {
        $row = DB::table('events')->where('event_id', $eventId)->first();

        return $row === null ? null : $this->toDto($row);
    }

    public function findChatCreatedEvent(string $chatId): ?ChatEventDto
    {
        $row = DB::table('events')
            ->where('chat_id', $chatId)
            ->where('type', 'chat.created')
            ->orderBy('sequence')
            ->first();

        return $row === null ? null : $this->toDto($row);
    }

    public function latestLogicalClockForOriginDevice(string $originDeviceId): ?int
    {
        $value = DB::table('events')
            ->where('origin_device_id', $originDeviceId)
            ->max('logical_clock');

        return $value === null ? null : (int) $value;
    }

    public function findSince(int $sequence, int $limit): array
    {
        return DB::table('events')
            ->where('sequence', '>', $sequence)
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): ChatEventDto => $this->toDto($row))
            ->all();
    }

    public function all(int $limit): array
    {
        return DB::table('events')
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): ChatEventDto => $this->toDto($row))
            ->all();
    }

    public function currentSequence(): int
    {
        return (int) DB::table('events')->max('sequence');
    }

    public function count(): int
    {
        return DB::table('events')->count();
    }

    private function toDto(object $row): ChatEventDto
    {
        try {
            $payload = is_string($row->payload_json)
                ? json_decode($row->payload_json, true, 512, JSON_THROW_ON_ERROR)
                : (array) $row->payload_json;
        } catch (JsonException) {
            $payload = [];
        }

        return ChatEventDto::fromArray([
            'eventId' => $row->event_id,
            'originNodeId' => $row->origin_node_id,
            'originDeviceId' => $row->origin_device_id,
            'actorUserId' => $row->actor_user_id,
            'chatId' => $row->chat_id,
            'type' => $row->type,
            'payload' => is_array($payload) ? $payload : [],
            'createdAt' => CarbonImmutable::parse($row->created_at, 'UTC')->toISOString(),
            'logicalClock' => (int) $row->logical_clock,
            'syncStatus' => $row->sync_status,
        ]);
    }
}
