<?php

namespace App\Infrastructure;

use App\Contracts\EventRepositoryInterface;
use App\Domain\Events\ChatEventDto;
use Illuminate\Support\Facades\DB;

final readonly class PostgresEventRepository implements EventRepositoryInterface
{
    public function __construct(private PostgresEventMapper $mapper) {}

    public function exists(string $eventId): bool
    {
        return DB::table('events')->where('event_id', $eventId)->exists();
    }

    public function store(ChatEventDto $event): void
    {
        DB::table('events')->insert($this->mapper->toRow($event));
    }

    public function findByEventId(string $eventId): ?ChatEventDto
    {
        $row = DB::table('events')->where('event_id', $eventId)->first();

        return $row === null ? null : $this->mapper->toDto($row);
    }

    public function findChatCreatedEvent(string $chatId): ?ChatEventDto
    {
        $row = DB::table('events')
            ->where('chat_id', $chatId)
            ->where('type', 'chat.created')
            ->orderBy('sequence')
            ->first();

        return $row === null ? null : $this->mapper->toDto($row);
    }

    public function maxLogicalClockForDevice(string $originDeviceId): ?int
    {
        $clock = DB::table('events')
            ->where('origin_device_id', $originDeviceId)
            ->max('logical_clock');

        return $clock === null ? null : (int) $clock;
    }

    public function findSince(int $sequence, int $limit = 100): array
    {
        return array_map(
            fn (array $row): ChatEventDto => $row['event'],
            $this->findSinceWithSequences($sequence, $limit),
        );
    }

    public function findSinceWithSequences(int $sequence, int $limit = 100): array
    {
        return DB::table('events')
            ->where('sequence', '>', $sequence)
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'sequence' => (int) $row->sequence,
                'event' => $this->mapper->toDto($row),
            ])
            ->all();
    }

    public function all(): array
    {
        return DB::table('events')
            ->orderBy('sequence')
            ->get()
            ->map(fn (object $row): ChatEventDto => $this->mapper->toDto($row))
            ->all();
    }

    public function currentSequence(): int
    {
        return (int) DB::table('events')->max('sequence');
    }
}
