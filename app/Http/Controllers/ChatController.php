<?php

namespace App\Http\Controllers;

use App\Contracts\ChatQueryRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class ChatController
{
    public function __construct(private ChatQueryRepositoryInterface $queries) {}

    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->query('userId', '');

        return response()->json($this->queries->listChats($userId));
    }
}
