<?php

namespace App\Infrastructure;

use App\Domain\Events\ChatEventDto;
use JsonException;

final readonly class PostgresEventMapper
{
    public function __construct(private PostgresDateTime $dateTime) {}

    /** @return array<string, mixed> */
    public function toRow(ChatEventDto $event): array
    {
        $clientCreatedAt = $this->dateTime->fromClientValue($event->createdAt);

        return [
            'event_id' => $event->eventId,
            'origin_node_id' => $event->originNodeId,
            'origin_device_id' => $event->originDeviceId,
            'actor_user_id' => $event->actorUserId,
            'chat_id' => $event->chatId,
            'type' => $event->type->value,
            'payload_json' => json_encode($event->payload, JSON_THROW_ON_ERROR),
            'created_at' => $clientCreatedAt,
            'client_created_at' => $clientCreatedAt,
            'central_received_at' => now()->utc()->toISOString(),
            'logical_clock' => $event->logicalClock,
            'sync_status' => $event->syncStatus->value,
        ];
    }

    public function toDto(object $row): ChatEventDto
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
            'createdAt' => $this->dateTime->toClientIso($row->client_created_at ?? $row->created_at),
            'logicalClock' => (int) $row->logical_clock,
            'syncStatus' => $row->sync_status,
        ]);
    }
}
