<?php

namespace App\Domain\Events;

use App\Domain\Chats\DirectChatKeyFactory;

final readonly class EventPayloadValidator
{
    private const MAX_TEXT_LENGTH = 2000;

    private const MAX_TITLE_LENGTH = 120;

    public function __construct(
        private DirectChatKeyFactory $directChatKeyFactory,
        private EventPayloadFields $fields,
    ) {}

    public function validate(ChatEventDto $event): void
    {
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

        $type = $this->fields->requireChatType($payload['type'] ?? null);
        $memberIds = $this->fields->requireStringArray($payload['memberIds'] ?? null, 'memberIds[]');
        $this->fields->validateOptionalString($payload, 'title', self::MAX_TITLE_LENGTH);
        $this->fields->validateOptionalString($payload, 'clientChatId');

        $uniqueMemberIds = array_values(array_unique([$event->actorUserId, ...$memberIds]));
        if ($type === 'direct') {
            $this->validateDirectPairKey($payload, $uniqueMemberIds);
        }

        if ($type === 'group' && count($uniqueMemberIds) < 2) {
            throw $this->fields->invalid('group chats must contain at least two unique participants including the actor.');
        }
    }

    private function validateMemberChanged(ChatEventDto $event): void
    {
        $this->validatePayloadChatId($event, $event->payload['chatId'] ?? null);
        $this->fields->requireString($event->payload['memberId'] ?? null, 'memberId');
    }

    private function validateMessageCreated(ChatEventDto $event): void
    {
        $payload = $event->payload;
        $this->validatePayloadChatId($event, $payload['chatId'] ?? null);
        $this->fields->requireString($payload['messageId'] ?? null, 'messageId');
        $this->fields->requireString($payload['clientMessageId'] ?? null, 'clientMessageId');
        $this->requireMessageText($payload['text'] ?? null);
    }

    private function validateMessageRead(ChatEventDto $event): void
    {
        $this->validatePayloadChatId($event, $event->payload['chatId'] ?? null);
        $this->fields->requireString($event->payload['messageId'] ?? null, 'messageId');
    }

    private function validatePayloadChatId(ChatEventDto $event, mixed $payloadChatId): void
    {
        $this->fields->requireString($payloadChatId, 'payload.chatId');
        if ($payloadChatId !== $event->chatId) {
            throw $this->fields->invalid('event.chatId must match payload.chatId.');
        }
    }

    /** @param array<string, mixed> $payload @param array<int, string> $memberIds */
    private function validateDirectPairKey(array $payload, array $memberIds): void
    {
        $directPairKey = $this->directChatKeyFactory->make($memberIds);
        if (isset($payload['directPairKey']) && $payload['directPairKey'] !== $directPairKey) {
            throw $this->fields->invalid('directPairKey must match the canonical sorted participant key.');
        }
    }

    private function requireMessageText(mixed $value): void
    {
        $this->fields->requireString($value, 'text');

        $text = trim((string) $value);
        if ($text === '') {
            throw $this->fields->invalid('message text cannot be empty.');
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw $this->fields->invalid('message text cannot exceed '.self::MAX_TEXT_LENGTH.' characters.');
        }
    }
}
