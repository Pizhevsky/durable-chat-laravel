<?php

namespace App\Domain\Chats;

enum ChatType: string
{
    case Direct = 'direct';
    case Group = 'group';
}
