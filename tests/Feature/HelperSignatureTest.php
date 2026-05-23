<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HelperSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_endpoint_rejects_unsigned_helper_requests(): void
    {
        $this->seed();

        $this->postJson('/api/sync/events', [
            'sourceNodeId' => 'helper-demo',
            'events' => [],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'missing_helper_signature');
    }

    public function test_sync_endpoint_accepts_signed_helper_requests(): void
    {
        $this->seed();

        $this->helperPostJson('/api/sync/events', [
            'sourceNodeId' => 'helper-demo',
            'events' => [],
        ])
            ->assertOk()
            ->assertJsonPath('centralNodeId', 'laravel-central');
    }

    public function test_recovery_import_rejects_unsigned_requests(): void
    {
        $this->seed();

        $this->postJson('/api/recovery/import?dryRun=true', [
            'format' => 'durable-chat-recovery-v1',
            'events' => [],
            'checksum' => hash('sha256', '[]'),
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'missing_helper_signature');
    }
}
