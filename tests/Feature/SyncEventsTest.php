<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SyncEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_helper_can_sync_a_chat_created_event_to_laravel_central(): void
    {
        $this->seed();

        $event = [
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
        ];

        $this->helperPostJson('/api/sync/events', [
            'sourceNodeId' => 'helper-demo',
            'events' => [$event],
        ])
            ->assertOk()
            ->assertJsonPath('accepted.0', 'device-1:event-1')
            ->assertJsonPath('centralNodeId', 'laravel-central');

        $this->assertDatabaseHas('events', [
            'event_id' => 'device-1:event-1',
            'sync_status' => 'central-synced',
        ]);
    }

    public function test_replaying_the_same_event_is_idempotent(): void
    {
        $this->seed();

        $event = [
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
        ];

        $this->helperPostJson('/api/sync/events', ['events' => [$event]])->assertOk();
        $this->helperPostJson('/api/sync/events', ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('duplicates.0', 'device-1:event-1');

        $this->assertDatabaseCount('events', 1);
    }

    public function test_event_created_at_is_stored_as_timestamptz_and_returned_as_utc_iso(): void
    {
        $this->seed();

        $event = [
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
            'createdAt' => '2026-05-20T22:00:00+12:00',
            'logicalClock' => 1,
            'syncStatus' => 'local',
        ];

        $this->helperPostJson('/api/sync/events', ['events' => [$event]])->assertOk();

        $this->helperGetJson('/api/sync/events?since=0&limit=1')
            ->assertOk()
            ->assertJsonPath('events.0.createdAt', '2026-05-20T10:00:00.000000Z');

        $this->assertDatabaseHas('events', [
            'event_id' => 'device-1:event-1',
        ]);
    }

    public function test_message_before_chat_is_reported_as_causal_conflict(): void
    {
        $this->seed();

        $event = [
            'eventId' => 'device-1:event-1',
            'originNodeId' => 'helper-demo',
            'originDeviceId' => 'device-1',
            'actorUserId' => 'u-denis',
            'chatId' => 'chat-missing',
            'type' => 'message.created',
            'payload' => [
                'chatId' => 'chat-missing',
                'messageId' => 'message-1',
                'clientMessageId' => 'client-message-1',
                'text' => 'This depends on a chat central has not accepted.',
            ],
            'createdAt' => '2026-05-20T10:00:00.000Z',
            'logicalClock' => 1,
            'syncStatus' => 'local',
        ];

        $this->helperPostJson('/api/sync/events', ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('conflicts.0.eventId', 'device-1:event-1')
            ->assertJsonPath('conflicts.0.code', 'CAUSAL_DEPENDENCY_MISSING')
            ->assertJsonPath('conflicts.0.category', 'causal_ordering')
            ->assertJsonPath('conflicts.0.retryable', true);

        $this->assertDatabaseMissing('events', ['event_id' => 'device-1:event-1']);
    }

    public function test_logical_clock_must_advance_per_device(): void
    {
        $this->seed();

        $chat = [
            'eventId' => 'device-1:event-2',
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
            'logicalClock' => 2,
            'syncStatus' => 'local',
        ];

        $staleMessage = [
            'eventId' => 'device-1:event-1',
            'originNodeId' => 'helper-demo',
            'originDeviceId' => 'device-1',
            'actorUserId' => 'u-denis',
            'chatId' => 'chat-1',
            'type' => 'message.created',
            'payload' => [
                'chatId' => 'chat-1',
                'messageId' => 'message-1',
                'clientMessageId' => 'client-message-1',
                'text' => 'This clock is stale.',
            ],
            'createdAt' => '2026-05-20T10:01:00.000Z',
            'logicalClock' => 1,
            'syncStatus' => 'local',
        ];

        $this->helperPostJson('/api/sync/events', ['events' => [$chat]])->assertOk();

        $this->helperPostJson('/api/sync/events', ['events' => [$staleMessage]])
            ->assertOk()
            ->assertJsonPath('conflicts.0.eventId', 'device-1:event-1')
            ->assertJsonPath('conflicts.0.code', 'LOGICAL_CLOCK_REGRESSION')
            ->assertJsonPath('conflicts.0.category', 'causal_ordering')
            ->assertJsonPath('conflicts.0.retryable', false);

        $this->assertDatabaseMissing('events', ['event_id' => 'device-1:event-1']);
    }
}
