<?php

namespace App\Contracts;

use App\Domain\Events\ChatEventDto;

interface EventRepositoryInterface
{
    public function exists(string $eventId): bool;

    public function store(ChatEventDto $event): void;

    public function findByEventId(string $eventId): ?ChatEventDto;

    public function findChatCreatedEvent(string $chatId): ?ChatEventDto;

    public function latestLogicalClockForOriginDevice(string $originDeviceId): ?int;

    /** @return array<int, ChatEventDto> */
    public function findSince(int $sequence, int $limit): array;

    /** @return array<int, ChatEventDto> */
    public function all(int $limit): array;

    public function currentSequence(): int;

    public function count(): int;
}
