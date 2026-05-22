<?php

namespace App\Domain\Events;

enum EventType: string
{
    case ChatCreated = 'chat.created';
    case MemberAdded = 'member.added';
    case MemberRemoved = 'member.removed';
    case MessageCreated = 'message.created';
    case MessageRead = 'message.read';
}
