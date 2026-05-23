<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecoveryDryRunTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_EVENTS_ENDPOINT = '/api/sync/events';

    private const RECOVERY_IMPORT_ENDPOINT = '/api/recovery/import';

    private const HELPER_NODE_ID = 'helper-demo';

    public function test_recovery_import_dry_run_reports_result_without_writing_to_database(): void
    {
        $this->seed();

        $existingEvent = $this->directChatEvent('device-1:event-1', 'chat-1', 'client-chat-1', 'u-denis', ['u-anna']);
        $newEvent = $this->directChatEvent('device-3:event-1', 'chat-3', 'client-chat-3', 'u-denis', ['u-kate']);
        $invalidEvent = $newEvent;
        $invalidEvent['eventId'] = 'invalid-event-id';

        $this->helperPostJson(self::SYNC_EVENTS_ENDPOINT, [
            'sourceNodeId' => self::HELPER_NODE_ID,
            'events' => [$existingEvent],
        ])->assertOk();

        $this->helperPostJson(self::RECOVERY_IMPORT_ENDPOINT.'?dryRun=true', $this->recoveryDump([
            $existingEvent,
            $newEvent,
            $invalidEvent,
        ]))
            ->assertOk()
            ->assertJsonPath('dryRun', true)
            ->assertJsonPath('duplicates.0', 'device-1:event-1')
            ->assertJsonPath('accepted.0', 'device-3:event-1')
            ->assertJsonPath('conflicts.0.eventId', 'invalid-event-id');

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseMissing('events', ['event_id' => 'device-3:event-1']);
    }

    public function test_recovery_import_applies_events_when_dry_run_is_not_enabled(): void
    {
        $this->seed();

        $newEvent = $this->directChatEvent('device-3:event-1', 'chat-3', 'client-chat-3', 'u-denis', ['u-kate']);

        $this->helperPostJson(self::RECOVERY_IMPORT_ENDPOINT, $this->recoveryDump([$newEvent]))
            ->assertOk()
            ->assertJsonPath('dryRun', false)
            ->assertJsonPath('accepted.0', 'device-3:event-1');

        $this->assertDatabaseHas('events', ['event_id' => 'device-3:event-1']);
        $this->assertDatabaseHas('chats', ['id' => 'chat-3']);
    }

    public function test_recovery_import_rejects_checksum_mismatch(): void
    {
        $this->seed();

        $event = $this->directChatEvent('device-4:event-1', 'chat-4', 'client-chat-4', 'u-denis', ['u-anna']);
        $dump = $this->recoveryDump([$event]);
        $dump['events'][0]['payload']['memberIds'] = ['u-denis', 'u-kate'];

        $this->helperPostJson(self::RECOVERY_IMPORT_ENDPOINT, $dump)
            ->assertStatus(422)
            ->assertJsonPath('code', 'RECOVERY_CHECKSUM_MISMATCH');

        $this->assertDatabaseMissing('events', ['event_id' => 'device-4:event-1']);
    }

    public function test_recovery_dry_run_uses_projection_rules_without_writing(): void
    {
        $this->seed();

        $event = $this->directChatEvent('device-5:event-1', 'chat-5', 'client-chat-5', 'u-denis', ['u-missing']);

        $this->helperPostJson(self::RECOVERY_IMPORT_ENDPOINT.'?dryRun=true', $this->recoveryDump([$event]))
            ->assertOk()
            ->assertJsonPath('dryRun', true)
            ->assertJsonPath('conflicts.0.eventId', 'device-5:event-1')
            ->assertJsonPath('conflicts.0.code', 'USER_NOT_FOUND')
            ->assertJsonPath('conflicts.0.category', 'missing_reference')
            ->assertJsonPath('conflicts.0.retryable', true);

        $this->assertDatabaseMissing('events', ['event_id' => 'device-5:event-1']);
        $this->assertDatabaseMissing('chats', ['id' => 'chat-5']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function recoveryDump(array $events): array
    {
        return [
            'format' => 'durable-chat-recovery-v1',
            'exportedAt' => '2026-05-20T10:00:00.000Z',
            'exportedBy' => 'u-denis',
            'deviceId' => 'browser-test',
            'events' => $events,
            'checksum' => hash('sha256', $this->canonicalJson($events)),
        ];
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->sortKeys($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param mixed $value @return mixed */
    private function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->sortKeys($item), $value);
    }

    /**
     * @param  array<int, string>  $memberIds
     * @return array<string, mixed>
     */
    private function directChatEvent(
        string $eventId,
        string $chatId,
        string $clientChatId,
        string $actorUserId,
        array $memberIds,
    ): array {
        return [
            'eventId' => $eventId,
            'originNodeId' => self::HELPER_NODE_ID,
            'originDeviceId' => explode(':', $eventId)[0],
            'actorUserId' => $actorUserId,
            'chatId' => $chatId,
            'type' => 'chat.created',
            'payload' => [
                'chatId' => $chatId,
                'clientChatId' => $clientChatId,
                'type' => 'direct',
                'memberIds' => $memberIds,
            ],
            'createdAt' => '2026-05-20T10:00:00.000Z',
            'logicalClock' => 1,
            'syncStatus' => 'local',
        ];
    }
}
