<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('role')->nullable();
        });

        Schema::create('chats', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('client_chat_id')->nullable()->unique();
            $table->string('direct_pair_key')->nullable();
            $table->string('type');
            $table->string('title')->nullable();
            $table->string('created_by');
            $table->dateTimeTz('created_at');
            $table->string('sync_status');
            $table->index(['type', 'created_at']);
            $table->foreign('created_by')->references('id')->on('users');
        });

        DB::statement('CREATE UNIQUE INDEX idx_chats_direct_pair_key ON chats(direct_pair_key) WHERE direct_pair_key IS NOT NULL');

        Schema::create('chat_members', function (Blueprint $table): void {
            $table->string('chat_id');
            $table->string('user_id');
            $table->dateTimeTz('joined_at');
            $table->dateTimeTz('left_at')->nullable();
            $table->boolean('is_owner')->default(false);
            $table->primary(['chat_id', 'user_id']);
            $table->index('user_id');
            $table->foreign('chat_id')->references('id')->on('chats')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('client_message_id');
            $table->string('chat_id');
            $table->string('sender_id');
            $table->text('text');
            $table->dateTimeTz('created_at');
            $table->string('sync_status');
            $table->unique(['sender_id', 'client_message_id']);
            $table->index(['chat_id', 'created_at']);
            $table->foreign('chat_id')->references('id')->on('chats')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users');
        });

        Schema::create('message_reads', function (Blueprint $table): void {
            $table->string('message_id');
            $table->string('user_id');
            $table->dateTimeTz('read_at');
            $table->primary(['message_id', 'user_id']);
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->string('event_id')->unique();
            $table->string('origin_node_id');
            $table->string('origin_device_id');
            $table->string('actor_user_id');
            $table->string('chat_id');
            $table->string('type');
            $table->jsonb('payload_json');
            $table->dateTimeTz('created_at');
            $table->unsignedBigInteger('logical_clock');
            $table->string('sync_status');
            $table->index('sequence');
            $table->index('sync_status');
            $table->index(['chat_id', 'type']);
            $table->index(['origin_device_id', 'logical_clock']);
        });

        Schema::create('peer_acks', function (Blueprint $table): void {
            $table->string('event_id');
            $table->string('peer_device_id');
            $table->dateTimeTz('acknowledged_at');
            $table->primary(['event_id', 'peer_device_id']);
        });

        Schema::create('node_sync_state', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_sync_state');
        Schema::dropIfExists('peer_acks');
        Schema::dropIfExists('events');
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('chat_members');
        Schema::dropIfExists('chats');
        Schema::dropIfExists('users');
    }
};
