<?php

namespace App\Contracts;

interface UserQueryRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function listUsers(): array;
}
