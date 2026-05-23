<?php

namespace App\Http\Controllers;

use App\Contracts\UserQueryRepositoryInterface;
use Illuminate\Http\JsonResponse;

final readonly class UserController
{
    public function __construct(private UserQueryRepositoryInterface $queries) {}

    public function index(): JsonResponse
    {
        return response()->json($this->queries->listUsers());
    }
}
