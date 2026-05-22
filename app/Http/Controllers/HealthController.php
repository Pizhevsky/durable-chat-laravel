<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'durable-chat-laravel-central',
            'centralNodeId' => config('durable-chat.central_node_id'),
        ]);
    }
}
