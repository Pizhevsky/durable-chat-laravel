<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            DB::table('events')->limit(1)->exists();

            return response()->json([
                'ok' => true,
                'service' => 'durable-chat-laravel',
                'centralNodeId' => config('durable-chat.central_node_id'),
                'checks' => [
                    'database' => 'ok',
                    'eventsTable' => 'ok',
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'ok' => false,
                'service' => 'durable-chat-laravel',
                'centralNodeId' => config('durable-chat.central_node_id'),
                'checks' => [
                    'database' => 'failed',
                    'eventsTable' => 'unknown',
                ],
                'error' => 'central_not_ready',
                'message' => $exception->getMessage(),
            ], 503);
        }
    }
}
