<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HelperContractTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_EVENTS_ENDPOINT = '/api/sync/events';

    private const READINESS_ENDPOINT = '/api/readiness';

    private const CENTRAL_NODE_ID = 'laravel-central';

    private const HELPER_NODE_ID = 'helper-demo';

    public function test_helper_sync_contract_accepts_events_and_returns_central_shape(): void
    {
        $this->seed();

        $response = $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => [$this->chatCreatedEvent()],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('accepted.0', 'device-1:event-1')
            ->assertJsonPath('duplicates', [])
            ->assertJsonPath('conflicts', [])
            ->assertJsonPath('centralNodeId', self::CENTRAL_NODE_ID)
            ->assertJsonPath('nodeRole', 'central')
            ->assertJsonPath('nodeId', self::CENTRAL_NODE_ID)
            ->assertJsonPath('dryRun', false)
            ->assertJsonStructure([
                'accepted',
                'duplicates',
                'conflicts',
                'serverEvents',
                'centralNodeId',
                'nodeRole',
                'nodeId',
                'dryRun',
            ]);

        self::assertArrayNotHasKey('helperUrl', $response->json());
    }

    public function test_helper_can_pull_missed_central_events_by_sequence_cursor(): void
    {
        $this->seed();

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => [$this->chatCreatedEvent()],
        ])->assertOk();

        $response = $this->helperGetJson(self::SYNC_EVENTS_ENDPOINT.'?since=0&limit=50')
            ->assertOk()
            ->assertJsonPath('centralNodeId', self::CENTRAL_NODE_ID)
            ->assertJsonPath('events.0.eventId', 'device-1:event-1')
            ->assertJsonPath('events.0.syncStatus', 'central-synced');

        self::assertSame($response->json('currentSequence'), $response->json('latestSequence'));
    }

    public function test_pull_cursor_advances_only_to_last_returned_event_so_helpers_do_not_skip_pages(): void
    {
        $this->seed();

        $events = [];
        for ($i = 1; $i <= 3; $i++) {
            $events[] = $this->groupChatCreatedEvent($i);
        }

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => $events,
        ])->assertOk();

        $firstPage = $this->helperGetJson(self::SYNC_EVENTS_ENDPOINT.'?since=0&limit=2')
            ->assertOk()
            ->assertJsonPath('hasMore', true)
            ->assertJsonCount(2, 'events');

        self::assertSame($firstPage->json('latestSequence') + 1, $firstPage->json('currentSequence'));

        $this->helperGetJson(self::SYNC_EVENTS_ENDPOINT.'?since='.$firstPage->json('latestSequence').'&limit=2')
            ->assertOk()
            ->assertJsonPath('hasMore', false)
            ->assertJsonCount(1, 'events');
    }

    public function test_readiness_endpoint_checks_database_access(): void
    {
        $this->seed();

        $this->getJson(self::READINESS_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('centralNodeId', self::CENTRAL_NODE_ID)
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.eventsTable', 'ok');
    }

    /** @return array<string, mixed> */
    private function groupChatCreatedEvent(int $index): array
    {
        return [
            'eventId' => "device-1:group-{$index}",
            'originNodeId' => self::HELPER_NODE_ID,
            'originDeviceId' => 'device-1',
            'actorUserId' => 'u-denis',
            'chatId' => "group-{$index}",
            'type' => 'chat.created',
            'payload' => [
                'chatId' => "group-{$index}",
                'clientChatId' => "client-group-{$index}",
                'type' => 'group',
                'title' => "Group {$index}",
                'memberIds' => ['u-anna'],
            ],
            'createdAt' => "2026-05-20T10:0{$index}:00.000Z",
            'logicalClock' => $index,
            'syncStatus' => 'local',
        ];
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
}
