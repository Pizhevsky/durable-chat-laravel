<?php

namespace App\Http\Controllers;

use App\Contracts\ChatListQueryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ChatController
{
    public function __construct(private ChatListQueryRepositoryInterface $queries) {}

    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->query('userId', '');

        return response()->json($this->queries->listChats($userId));
    }
}
