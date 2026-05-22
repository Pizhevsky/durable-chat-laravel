<?php

namespace App\Http\Controllers;

use App\Contracts\ChatQueryRepositoryInterface;
use Illuminate\Http\JsonResponse;

final readonly class UserController
{
    public function __construct(private ChatQueryRepositoryInterface $queries) {}

    public function index(): JsonResponse
    {
        return response()->json($this->queries->listUsers());
    }
}
