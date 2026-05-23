<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MultiHelperDirectChatReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_EVENTS_ENDPOINT = '/api/sync/events';

    public function test_second_helper_direct_chat_and_message_are_reconciled_to_authoritative_central_chat(): void
    {
        $this->seed();

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => 'helper-a',
            'events' => [$this->directChat('helper-a:chat-created', 'helper-a', 'chat-a', 'u-denis', ['u-anna'])],
        ])->assertOk()->assertJsonPath('accepted.0', 'helper-a:chat-created');

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => 'helper-b',
            'events' => [
                $this->directChat('helper-b:chat-created', 'helper-b', 'chat-b', 'u-anna', ['u-denis']),
                $this->message('helper-b:message-created', 'helper-b', 'chat-b', 'u-anna'),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('accepted.0', 'helper-b:message-created')
            ->assertJsonPath('duplicates.0', 'helper-b:chat-created')
            ->assertJsonPath('serverEvents.0.chatId', 'chat-a')
            ->assertJsonPath('serverEvents.1.chatId', 'chat-a')
            ->assertJsonPath('serverEvents.1.payload.chatId', 'chat-a');

        $this->assertDatabaseCount('chats', 1);
        $this->assertDatabaseHas('messages', [
            'id' => 'message-helper-b',
            'chat_id' => 'chat-a',
            'sender_id' => 'u-anna',
            'text' => 'Message from the second helper after offline direct chat duplication',
        ]);
        $this->assertDatabaseHas('events', [
            'event_id' => 'helper-b:message-created',
            'chat_id' => 'chat-a',
        ]);
    }

    /** @return array<string, mixed> */
    private function directChat(string $eventId, string $helperId, string $chatId, string $actorUserId, array $memberIds): array
    {
        return [
            'eventId' => $eventId,
            'originNodeId' => $helperId,
            'originDeviceId' => $helperId.'-device',
            'actorUserId' => $actorUserId,
            'chatId' => $chatId,
            'type' => 'chat.created',
            'payload' => [
                'chatId' => $chatId,
                'clientChatId' => $chatId,
                'type' => 'direct',
                'memberIds' => $memberIds,
            ],
            'createdAt' => '2026-05-20T10:00:00.000Z',
            'logicalClock' => 1,
            'syncStatus' => 'local',
        ];
    }

    /** @return array<string, mixed> */
    private function message(string $eventId, string $helperId, string $chatId, string $actorUserId): array
    {
        return [
            'eventId' => $eventId,
            'originNodeId' => $helperId,
            'originDeviceId' => $helperId.'-device',
            'actorUserId' => $actorUserId,
            'chatId' => $chatId,
            'type' => 'message.created',
            'payload' => [
                'chatId' => $chatId,
                'messageId' => 'message-helper-b',
                'clientMessageId' => 'client-message-helper-b',
                'text' => 'Message from the second helper after offline direct chat duplication',
            ],
            'createdAt' => '2026-05-20T10:01:00.000Z',
            'logicalClock' => 2,
            'syncStatus' => 'local',
        ];
    }
}
