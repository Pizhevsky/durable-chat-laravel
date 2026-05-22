<?php

namespace App\Domain\Events;

enum EventSyncStatus: string
{
    case Local = 'local';
    case PeerReplicated = 'peer-replicated';
    case HelperSynced = 'helper-synced';
    case CentralSynced = 'central-synced';
    case Conflict = 'conflict';

    public static function forCentralStorage(): self
    {
        return self::CentralSynced;
    }
}
