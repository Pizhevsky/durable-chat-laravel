<?php

namespace App\Domain\Events;

use App\Domain\Chats\DirectChatKeyFactory;
use App\Domain\Shared\DomainRuleException;

final readonly class EventValidator
{
    private const MAX_TEXT_LENGTH = 2000;

    private const MAX_TITLE_LENGTH = 120;

    private const EVENT_ID_PATTERN = '/^[^:\s]+:[^:\s]+$/';

    private const ISO_DATE_TIME_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/';

    public function __construct(private DirectChatKeyFactory $directChatKeyFactory) {}

    public function validate(ChatEventDto $event): void
    {
        if (! preg_match(self::EVENT_ID_PATTERN, $event->eventId)) {
            throw $this->invalid('eventId must be in originDeviceId:eventId format.');
        }

        if ($event->logicalClock < 0) {
            throw $this->invalid('logicalClock must be a non-negative integer.');
        }

        if (! preg_match(self::ISO_DATE_TIME_PATTERN, $event->createdAt) || strtotime($event->createdAt) === false) {
            throw $this->invalid('createdAt must be an ISO 8601 date string.');
        }

        match ($event->type) {
            EventType::ChatCreated => $this->validateChatCreated($event),
            EventType::MemberAdded,
            EventType::MemberRemoved => $this->validateMemberChanged($event),
            EventType::MessageCreated => $this->validateMessageCreated($event),
            EventType::MessageRead => $this->validateMessageRead($event),
        };
    }

    private function validateChatCreated(ChatEventDto $event): void
    {
        $payload = $event->payload;
        $this->validatePayloadChatId($event, $payload['chatId'] ?? null);

        $type = $payload['type'] ?? null;
        if ($type !== 'direct' && $type !== 'group') {
            throw $this->invalid('chat.created payload type must be direct or group.');
        }

        $memberIds = $payload['memberIds'] ?? null;
        if (! is_array($memberIds) || $memberIds === []) {
            throw $this->invalid('chat.created memberIds must be a non-empty string array.');
        }

        foreach ($memberIds as $memberId) {
            $this->requireString($memberId, 'memberIds[]');
        }

        if (array_key_exists('title', $payload) && $payload['title'] !== null) {
            if (! is_string($payload['title']) || mb_strlen($payload['title']) > self::MAX_TITLE_LENGTH) {
                throw $this->invalid('chat title must be a string no longer than '.self::MAX_TITLE_LENGTH.' characters.');
            }
        }

        if (array_key_exists('clientChatId', $payload) && $payload['clientChatId'] !== null) {
            $this->requireString($payload['clientChatId'], 'clientChatId');
        }

        $uniqueMemberIds = array_values(array_unique([$event->actorUserId, ...$memberIds]));
        if ($type === 'direct') {
            $directPairKey = $this->directChatKeyFactory->make($uniqueMemberIds);
            if (isset($payload['directPairKey']) && $payload['directPairKey'] !== $directPairKey) {
                throw $this->invalid('directPairKey must match the canonical sorted participant key.');
            }
        }

        if ($type === 'group' && count($uniqueMemberIds) < 2) {
            throw $this->invalid('group chats must contain at least two unique participants including the actor.');
        }
    }

    private function validateMemberChanged(ChatEventDto $event): void
    {
        $this->validatePayloadChatId($event, $event->payload['chatId'] ?? null);
        $this->requireString($event->payload['memberId'] ?? null, 'memberId');
    }

    private function validateMessageCreated(ChatEventDto $event): void
    {
        $payload = $event->payload;
        $this->validatePayloadChatId($event, $payload['chatId'] ?? null);
        $this->requireString($payload['messageId'] ?? null, 'messageId');
        $this->requireString($payload['clientMessageId'] ?? null, 'clientMessageId');
        $this->requireString($payload['text'] ?? null, 'text');

        $text = trim((string) $payload['text']);
        if ($text === '') {
            throw $this->invalid('message text cannot be empty.');
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw $this->invalid('message text cannot exceed '.self::MAX_TEXT_LENGTH.' characters.');
        }
    }

    private function validateMessageRead(ChatEventDto $event): void
    {
        $this->validatePayloadChatId($event, $event->payload['chatId'] ?? null);
        $this->requireString($event->payload['messageId'] ?? null, 'messageId');
    }

    private function validatePayloadChatId(ChatEventDto $event, mixed $payloadChatId): void
    {
        $this->requireString($payloadChatId, 'payload.chatId');
        if ($payloadChatId !== $event->chatId) {
            throw $this->invalid('event.chatId must match payload.chatId.');
        }
    }

    private function requireString(mixed $value, string $fieldName): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw $this->invalid("{$fieldName} must be a non-empty string.");
        }
    }

    private function invalid(string $message): DomainRuleException
    {
        return new DomainRuleException($message, 422, 'INVALID_EVENT');
    }
}
