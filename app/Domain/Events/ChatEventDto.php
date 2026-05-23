<?php

namespace App\Domain\Events;

use App\Domain\Shared\DomainRuleException;

final readonly class ChatEventDto
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $eventId,
        public string $originNodeId,
        public string $originDeviceId,
        public string $actorUserId,
        public string $chatId,
        public EventType $type,
        public array $payload,
        public string $createdAt,
        public int $logicalClock,
        public EventSyncStatus $syncStatus,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $type = isset($data['type']) && is_string($data['type']) ? EventType::tryFrom($data['type']) : null;
        $status = isset($data['syncStatus']) && is_string($data['syncStatus'])
            ? EventSyncStatus::tryFrom($data['syncStatus'])
            : EventSyncStatus::Local;

        if ($type === null) {
            throw new DomainRuleException('Unsupported event type: '.(string) ($data['type'] ?? ''), 422, 'INVALID_EVENT_TYPE');
        }

        return new self(
            eventId: self::stringField($data, 'eventId'),
            originNodeId: self::stringField($data, 'originNodeId'),
            originDeviceId: self::stringField($data, 'originDeviceId'),
            actorUserId: self::stringField($data, 'actorUserId'),
            chatId: self::stringField($data, 'chatId'),
            type: $type,
            payload: self::arrayField($data, 'payload'),
            createdAt: self::stringField($data, 'createdAt'),
            logicalClock: self::intField($data, 'logicalClock'),
            syncStatus: $status,
        );
    }

    public function withActor(string $actorUserId): self
    {
        return $this->copy(actorUserId: $actorUserId);
    }

    public function withChatId(string $chatId): self
    {
        $payload = $this->payload;
        if (array_key_exists('chatId', $payload)) {
            $payload['chatId'] = $chatId;
        }

        return $this->copy(chatId: $chatId, payload: $payload);
    }

    public function withSyncStatus(EventSyncStatus $syncStatus): self
    {
        return $this->copy(syncStatus: $syncStatus);
    }

    /** @param array<string, mixed>|null $payload */
    private function copy(
        ?string $actorUserId = null,
        ?string $chatId = null,
        ?array $payload = null,
        ?EventSyncStatus $syncStatus = null,
    ): self {
        return new self(
            $this->eventId,
            $this->originNodeId,
            $this->originDeviceId,
            $actorUserId ?? $this->actorUserId,
            $chatId ?? $this->chatId,
            $this->type,
            $payload ?? $this->payload,
            $this->createdAt,
            $this->logicalClock,
            $syncStatus ?? $this->syncStatus,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'originNodeId' => $this->originNodeId,
            'originDeviceId' => $this->originDeviceId,
            'actorUserId' => $this->actorUserId,
            'chatId' => $this->chatId,
            'type' => $this->type->value,
            'payload' => $this->payload,
            'createdAt' => $this->createdAt,
            'logicalClock' => $this->logicalClock,
            'syncStatus' => $this->syncStatus->value,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function stringField(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new DomainRuleException("{$field} must be a non-empty string.", 422, 'INVALID_EVENT');
        }

        return $value;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function arrayField(array $data, string $field): array
    {
        $value = $data[$field] ?? null;
        if (! is_array($value)) {
            throw new DomainRuleException("{$field} must be an object.", 422, 'INVALID_EVENT');
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private static function intField(array $data, string $field): int
    {
        $value = $data[$field] ?? null;
        if (! is_int($value) && ! (is_numeric($value) && (string) (int) $value === (string) $value)) {
            throw new DomainRuleException("{$field} must be an integer.", 422, 'INVALID_EVENT');
        }

        return (int) $value;
    }
}
