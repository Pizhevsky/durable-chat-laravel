<?php

namespace App\Http\Controllers;

use App\Application\Messages\ListMessagesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class MessageController
{
    public function __construct(private ListMessagesService $messages) {}

    public function index(Request $request, string $chatId): JsonResponse
    {
        $userId = (string) $request->query('userId', '');

        return response()->json($this->messages->list($chatId, $userId));
    }
}
