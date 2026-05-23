<?php

namespace App\Domain\Chats;

final class DirectChatKeyFactory
{
    /** @param array<int, string> $userIds */
    public function make(array $userIds): string
    {
        return DirectPairKey::fromUserIds($userIds)->value;
    }
}
