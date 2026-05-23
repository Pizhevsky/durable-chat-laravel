<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdempotencyProjectionTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_EVENTS_ENDPOINT = '/api/sync/events';

    private const HELPER_NODE_ID = 'helper-demo';

    public function test_retrying_the_same_message_event_does_not_duplicate_event_or_projection_rows(): void
    {
        $this->seed();

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => [$this->chatCreatedEvent()],
        ])->assertOk();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
                'sourceNodeId' => self::HELPER_NODE_ID,
                'events' => [$this->messageCreatedEvent()],
            ])->assertOk();
        }

        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('message_reads', 1);
        $this->assertDatabaseHas('messages', [
            'id' => 'message-1',
            'chat_id' => 'chat-1',
            'sender_id' => 'u-denis',
            'text' => 'Hello through the helper path',
            'sync_status' => 'central-synced',
        ]);
    }

    public function test_retried_batch_reports_duplicate_event_ids_to_the_helper(): void
    {
        $this->seed();

        $events = [$this->chatCreatedEvent(), $this->messageCreatedEvent()];

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => $events,
        ])->assertOk();

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => $events,
        ])
            ->assertOk()
            ->assertJsonPath('duplicates.0', 'device-1:event-1')
            ->assertJsonPath('duplicates.1', 'device-1:event-2')
            ->assertJsonPath('accepted', []);

        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseCount('messages', 1);
    }

    /** @return array<string, mixed> */
    private function chatCreatedEvent(): array
    {
        return [
            'eventId' => 'device-1:event-1',
            'originNodeId' => self::HELPER_NODE_ID,
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
        ];
    }

    /** @return array<string, mixed> */
    private function messageCreatedEvent(): array
    {
        return [
            'eventId' => 'device-1:event-2',
            'originNodeId' => self::HELPER_NODE_ID,
            'originDeviceId' => 'device-1',
            'actorUserId' => 'u-denis',
            'chatId' => 'chat-1',
            'type' => 'message.created',
            'payload' => [
                'chatId' => 'chat-1',
                'messageId' => 'message-1',
                'clientMessageId' => 'client-message-1',
                'text' => 'Hello through the helper path',
            ],
            'createdAt' => '2026-05-20T10:01:00.000Z',
            'logicalClock' => 2,
            'syncStatus' => 'local',
        ];
    }
}
