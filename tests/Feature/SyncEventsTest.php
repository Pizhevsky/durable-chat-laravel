<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ChatEventPayloadFactory;
use Tests\TestCase;

final class SyncEventsTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_EVENTS_ENDPOINT = '/api/sync/events';

    public function test_helper_can_sync_a_chat_created_event_to_laravel_central(): void
    {
        $this->seed();

        $event = ChatEventPayloadFactory::chatCreated();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, [
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

        $event = ChatEventPayloadFactory::chatCreated();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [$event]])->assertOk();
        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('duplicates.0', 'device-1:event-1');

        $this->assertDatabaseCount('events', 1);
    }

    public function test_direct_chat_replay_with_a_new_event_id_returns_existing_event_as_duplicate(): void
    {
        $this->seed();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [ChatEventPayloadFactory::chatCreated()]])->assertOk();

        $duplicatePair = ChatEventPayloadFactory::chatCreated([
            'eventId' => 'device-1:event-2',
            'chatId' => 'chat-2',
            'logicalClock' => 2,
            'payload' => [
                'chatId' => 'chat-2',
                'clientChatId' => 'client-chat-2',
                'type' => 'direct',
                'memberIds' => ['u-anna'],
            ],
        ]);

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [$duplicatePair]])
            ->assertOk()
            ->assertJsonPath('duplicates.0', 'device-1:event-2')
            ->assertJsonPath('serverEvents.0.eventId', 'device-1:event-1');

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('chats', 1);
    }

    public function test_message_before_chat_is_reported_as_causal_conflict(): void
    {
        $this->seed();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [ChatEventPayloadFactory::messageCreated()]])
            ->assertOk()
            ->assertJsonPath('conflicts.0.eventId', 'device-1:event-2')
            ->assertJsonPath('conflicts.0.code', 'CAUSAL_DEPENDENCY_MISSING')
            ->assertJsonPath('conflicts.0.category', 'causal_ordering')
            ->assertJsonPath('conflicts.0.retryable', true)
            ->assertJsonPath('conflictIds.0', 'device-1:event-2')
            ->assertJsonPath('meta.counts.conflicts', 1);

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_logical_clock_must_advance_for_each_origin_device(): void
    {
        $this->seed();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [ChatEventPayloadFactory::chatCreated(['logicalClock' => 2])]])
            ->assertOk();

        $regressed = ChatEventPayloadFactory::messageCreated(['logicalClock' => 1]);

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [$regressed]])
            ->assertOk()
            ->assertJsonPath('conflicts.0.code', 'CAUSAL_CLOCK_REGRESSION')
            ->assertJsonPath('conflicts.0.category', 'causal_ordering')
            ->assertJsonPath('conflicts.0.retryable', true);

        $this->assertDatabaseCount('events', 1);
    }

    public function test_missing_user_conflict_is_retryable_after_central_seed_is_fixed(): void
    {
        $event = ChatEventPayloadFactory::chatCreated();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [$event]])
            ->assertOk()
            ->assertJsonPath('conflicts.0.code', 'USER_NOT_FOUND')
            ->assertJsonPath('conflicts.0.category', 'missing_reference')
            ->assertJsonPath('conflicts.0.retryable', true)
            ->assertJsonPath('conflictIds.0', 'device-1:event-1');

        $this->assertDatabaseCount('events', 0);
    }

    public function test_batch_order_allows_chat_then_message_replay(): void
    {
        $this->seed();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => 'helper-demo',
            'events' => [
                ChatEventPayloadFactory::chatCreated(),
                ChatEventPayloadFactory::messageCreated(),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('accepted.0', 'device-1:event-1')
            ->assertJsonPath('accepted.1', 'device-1:event-2')
            ->assertJsonPath('meta.orderingPolicy', 'batch-order-with-per-device-logical-clock')
            ->assertJsonPath('meta.counts.accepted', 2);

        $this->assertDatabaseCount('events', 2);
        $this->assertDatabaseHas('messages', ['id' => 'message-1']);
    }

    public function test_pull_sync_is_limited(): void
    {
        $this->seed();
        $this->postJson(self::SYNC_EVENTS_ENDPOINT, [
            'events' => [
                ChatEventPayloadFactory::chatCreated(),
                ChatEventPayloadFactory::messageCreated(),
            ],
        ])->assertOk();

        $response = $this->getJson(self::SYNC_EVENTS_ENDPOINT.'?since=0&limit=1')
            ->assertOk()
            ->assertJsonPath('limit', 1)
            ->json();

        self::assertCount(1, $response['events']);
    }

    public function test_event_timestamps_are_returned_as_utc_iso_strings(): void
    {
        $this->seed();

        $this->postJson(self::SYNC_EVENTS_ENDPOINT, [
            'events' => [ChatEventPayloadFactory::chatCreated(['createdAt' => '2026-05-20T22:00:00+12:00'])],
        ])->assertOk();

        $this->getJson(self::SYNC_EVENTS_ENDPOINT.'?since=0&limit=1')
            ->assertOk()
            ->assertJsonPath('events.0.createdAt', '2026-05-20T10:00:00.000000Z');
    }
}
