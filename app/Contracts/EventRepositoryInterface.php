<?php

namespace App\Contracts;

use App\Domain\Events\ChatEventDto;

interface EventRepositoryInterface
{
    public function exists(string $eventId): bool;

    public function store(ChatEventDto $event): void;

    public function findByEventId(string $eventId): ?ChatEventDto;

    public function findChatCreatedEvent(string $chatId): ?ChatEventDto;

    public function maxLogicalClockForDevice(string $originDeviceId): ?int;

    /** @return array<int, ChatEventDto> */
    public function findSince(int $sequence, int $limit = 100): array;

    /** @return list<array{sequence: int, event: ChatEventDto}> */
    public function findSinceWithSequences(int $sequence, int $limit = 100): array;

    /** @return array<int, ChatEventDto> */
    public function all(): array;

    public function currentSequence(): int;
}
