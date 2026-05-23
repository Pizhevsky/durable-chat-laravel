<?php

namespace App\Infrastructure;

use App\Domain\Events\ChatEventDto;

final readonly class PostgresProjectionMapper
{
    public function __construct(private PostgresDateTime $dateTime) {}

    /** @return array<string, mixed> */
    public function chatRow(ChatEventDto $event, ?string $directPairKey): array
    {
        return [
            'id' => $event->payload['chatId'],
            'client_chat_id' => $event->payload['clientChatId'] ?? null,
            'direct_pair_key' => $directPairKey,
            'type' => $event->payload['type'],
            'title' => isset($event->payload['title']) ? trim((string) $event->payload['title']) : null,
            'created_by' => $event->actorUserId,
            'created_at' => $this->dateTime->fromClientValue($event->createdAt),
            'sync_status' => $event->syncStatus->value,
        ];
    }

    /** @return array<string, mixed> */
    public function memberRow(string $chatId, string $memberId, string $joinedAt, bool $isOwner): array
    {
        return [
            'chat_id' => $chatId,
            'user_id' => $memberId,
            'joined_at' => $this->dateTime->fromClientValue($joinedAt),
            'left_at' => null,
            'is_owner' => $isOwner,
        ];
    }

    /** @return array<string, mixed> */
    public function messageRow(ChatEventDto $event): array
    {
        return [
            'id' => $event->payload['messageId'],
            'client_message_id' => $event->payload['clientMessageId'],
            'chat_id' => $event->payload['chatId'],
            'sender_id' => $event->actorUserId,
            'text' => $event->payload['text'],
            'created_at' => $this->dateTime->fromClientValue($event->createdAt),
            'sync_status' => $event->syncStatus->value,
        ];
    }

    /** @return array<string, mixed> */
    public function messageReadRow(string $messageId, string $userId, string $readAt): array
    {
        return [
            'message_id' => $messageId,
            'user_id' => $userId,
            'read_at' => $this->dateTime->fromClientValue($readAt),
        ];
    }
}
