<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ChatEventPayloadFactory;
use Tests\TestCase;

final class RecoveryEventsTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_EVENTS_ENDPOINT = '/api/sync/events';

    private const RECOVERY_EXPORT_ENDPOINT = '/api/recovery/export';

    private const RECOVERY_IMPORT_ENDPOINT = '/api/recovery/import';

    public function test_recovery_export_includes_replay_guarantees_and_import_is_idempotent(): void
    {
        $this->seed();
        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [ChatEventPayloadFactory::chatCreated()]])->assertOk();

        $export = $this->getJson(self::RECOVERY_EXPORT_ENDPOINT.'?userId=u-denis&deviceId=device-1')
            ->assertOk()
            ->assertJsonPath('eventCount', 1)
            ->assertJsonPath('orderingPolicy', 'central-sequence-ascending')
            ->json();

        self::assertIsString($export['checksum'] ?? null);
        self::assertIsInt($export['latestSequence'] ?? null);
        self::assertGreaterThanOrEqual(1, $export['latestSequence']);

        $this->postJson(self::RECOVERY_IMPORT_ENDPOINT, $export)
            ->assertOk()
            ->assertJsonPath('duplicates.0', 'device-1:event-1')
            ->assertJsonPath('meta.sourceNodeId', 'device-1');

        $this->assertDatabaseCount('events', 1);
    }

    public function test_recovery_import_rejects_checksum_mismatch(): void
    {
        $this->seed();
        $this->postJson(self::SYNC_EVENTS_ENDPOINT, ['events' => [ChatEventPayloadFactory::chatCreated()]])->assertOk();

        $export = $this->getJson(self::RECOVERY_EXPORT_ENDPOINT.'?userId=u-denis&deviceId=device-1')
            ->assertOk()
            ->json();

        $export['events'][0]['logicalClock'] = 999;

        $this->postJson(self::RECOVERY_IMPORT_ENDPOINT, $export)
            ->assertStatus(422)
            ->assertJsonPath('code', 'CHECKSUM_MISMATCH');
    }
}
