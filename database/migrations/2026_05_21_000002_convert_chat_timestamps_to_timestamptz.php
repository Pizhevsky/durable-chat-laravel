<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE chats ALTER COLUMN created_at TYPE timestamptz USING created_at::timestamptz');
        DB::statement('ALTER TABLE chat_members ALTER COLUMN joined_at TYPE timestamptz USING joined_at::timestamptz');
        DB::statement('ALTER TABLE chat_members ALTER COLUMN left_at TYPE timestamptz USING left_at::timestamptz');
        DB::statement('ALTER TABLE messages ALTER COLUMN created_at TYPE timestamptz USING created_at::timestamptz');
        DB::statement('ALTER TABLE message_reads ALTER COLUMN read_at TYPE timestamptz USING read_at::timestamptz');
        DB::statement('ALTER TABLE events ALTER COLUMN created_at TYPE timestamptz USING created_at::timestamptz');
        DB::statement('ALTER TABLE peer_acks ALTER COLUMN acknowledged_at TYPE timestamptz USING acknowledged_at::timestamptz');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE peer_acks ALTER COLUMN acknowledged_at TYPE varchar(255) USING acknowledged_at::text');
        DB::statement('ALTER TABLE events ALTER COLUMN created_at TYPE varchar(255) USING created_at::text');
        DB::statement('ALTER TABLE message_reads ALTER COLUMN read_at TYPE varchar(255) USING read_at::text');
        DB::statement('ALTER TABLE messages ALTER COLUMN created_at TYPE varchar(255) USING created_at::text');
        DB::statement('ALTER TABLE chat_members ALTER COLUMN left_at TYPE varchar(255) USING left_at::text');
        DB::statement('ALTER TABLE chat_members ALTER COLUMN joined_at TYPE varchar(255) USING joined_at::text');
        DB::statement('ALTER TABLE chats ALTER COLUMN created_at TYPE varchar(255) USING created_at::text');
    }
};
