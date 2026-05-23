<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DirectChatDuplicateProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_EVENTS_ENDPOINT = '/api/sync/events';

    private const HELPER_NODE_ID = 'helper-demo';

    public function test_same_direct_pair_does_not_create_two_central_chats_even_with_different_event_ids(): void
    {
        $this->seed();

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => [$this->directChatEventFromDenis()],
        ])->assertOk()->assertJsonPath('accepted.0', 'device-1:event-1');

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => [$this->sameDirectPairEventFromAnna()],
        ])
            ->assertOk()
            ->assertJsonPath('accepted', [])
            ->assertJsonPath('duplicates.0', 'device-2:event-1');

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('chats', 1);
        $this->assertDatabaseCount('chat_members', 2);
        $this->assertDatabaseHas('chats', [
            'id' => 'chat-1',
            'direct_pair_key' => 'u-anna:u-denis',
        ]);
    }

    /** @return array<string, mixed> */
    private function directChatEventFromDenis(): array
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
    private function sameDirectPairEventFromAnna(): array
    {
        return [
            'eventId' => 'device-2:event-1',
            'originNodeId' => self::HELPER_NODE_ID,
            'originDeviceId' => 'device-2',
            'actorUserId' => 'u-anna',
            'chatId' => 'chat-2',
            'type' => 'chat.created',
            'payload' => [
                'chatId' => 'chat-2',
                'clientChatId' => 'client-chat-2',
                'type' => 'direct',
                'memberIds' => ['u-denis'],
            ],
            'createdAt' => '2026-05-20T10:00:05.000Z',
            'logicalClock' => 1,
            'syncStatus' => 'local',
        ];
    }
}
