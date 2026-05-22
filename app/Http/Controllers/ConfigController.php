<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ConfigController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'centralNodeId' => config('durable-chat.central_node_id'),
            'centralUrl' => config('app.url'),
            'vapidPublicKey' => null,
        ]);
    }
}
