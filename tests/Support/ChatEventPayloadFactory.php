<?php

namespace Tests\Support;

final class ChatEventPayloadFactory
{
    /** @param array<string, mixed> $overrides */
    public static function chatCreated(array $overrides = []): array
    {
        return array_replace_recursive([
            'eventId' => 'device-1:event-1',
            'originNodeId' => 'helper-demo',
            'originDeviceId' => 'device-1',
            'actorUserId' => 'u-denis',
            'chatId' => 'chat-1',
            'type' => 'chat.created',
            'payload' => [
                'chatId' => 'chat-1',
                'clientChatId' => 'client-chat-1',
                'type' => 'direct',
                'memberIds' => ['u-anna'],
            ],
            'createdAt' => '2026-05-20T10:00:00.000Z',
            'logicalClock' => 1,
            'syncStatus' => 'local',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    public static function messageCreated(array $overrides = []): array
    {
        return array_replace_recursive([
            'eventId' => 'device-1:event-2',
            'originNodeId' => 'helper-demo',
            'originDeviceId' => 'device-1',
            'actorUserId' => 'u-denis',
            'chatId' => 'chat-1',
            'type' => 'message.created',
            'payload' => [
                'chatId' => 'chat-1',
                'messageId' => 'message-1',
                'clientMessageId' => 'client-message-1',
                'text' => 'Hello from helper',
            ],
            'createdAt' => '2026-05-20T10:01:00.000Z',
            'logicalClock' => 2,
            'syncStatus' => 'local',
        ], $overrides);
    }
}
