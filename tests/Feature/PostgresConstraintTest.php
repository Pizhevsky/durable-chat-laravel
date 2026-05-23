<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_duplicate_direct_pair_keys_below_the_service_layer(): void
    {
        $this->seed();

        DB::table('chats')->insert($this->chatRow('chat-1', 'client-chat-1'));

        $this->expectException(QueryException::class);

        DB::table('chats')->insert($this->chatRow('chat-2', 'client-chat-2'));
    }

    public function test_database_rejects_unknown_event_types(): void
    {
        $this->seed();

        $this->expectException(QueryException::class);

        DB::table('events')->insert([
            'event_id' => 'device-1:event-1',
            'origin_node_id' => 'helper-demo',
            'origin_device_id' => 'device-1',
            'actor_user_id' => 'u-denis',
            'chat_id' => 'chat-1',
            'type' => 'unsupported.event',
            'payload_json' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => '2026-05-20T10:00:00.000Z',
            'logical_clock' => 1,
            'sync_status' => 'central-synced',
        ]);
    }

    public function test_timestamp_columns_use_postgres_timestamptz(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', ['chats', 'chat_members', 'messages', 'message_reads', 'events', 'peer_acks'])
            ->whereIn('column_name', [
                'created_at',
                'joined_at',
                'left_at',
                'read_at',
                'acknowledged_at',
                'client_created_at',
                'central_received_at',
            ])
            ->selectRaw("table_name || '.' || column_name as column_key, data_type")
            ->pluck('data_type', 'column_key');

        self::assertSame('timestamp with time zone', $columns['chats.created_at']);
        self::assertSame('timestamp with time zone', $columns['chat_members.joined_at']);
        self::assertSame('timestamp with time zone', $columns['chat_members.left_at']);
        self::assertSame('timestamp with time zone', $columns['messages.created_at']);
        self::assertSame('timestamp with time zone', $columns['message_reads.read_at']);
        self::assertSame('timestamp with time zone', $columns['events.created_at']);
        self::assertSame('timestamp with time zone', $columns['events.client_created_at']);
        self::assertSame('timestamp with time zone', $columns['events.central_received_at']);
        self::assertSame('timestamp with time zone', $columns['peer_acks.acknowledged_at']);
    }

    /** @return array<string, mixed> */
    private function chatRow(string $id, string $clientChatId): array
    {
        return [
            'id' => $id,
            'client_chat_id' => $clientChatId,
            'direct_pair_key' => 'u-anna:u-denis',
            'type' => 'direct',
            'title' => null,
            'created_by' => 'u-denis',
            'created_at' => '2026-05-20T10:00:00.000Z',
            'sync_status' => 'central-synced',
        ];
    }
}
