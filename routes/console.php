<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('durable-chat:about', function (): void {
    $this->info('Durable Chat Relay Laravel central server');
    $this->line('Laravel stores the authoritative event log and PostgreSQL projections.');
    $this->line('The original Node.js helper remains the local resilience layer.');
});
