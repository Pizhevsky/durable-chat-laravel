<?php

namespace App\Application\Sync;

final readonly class SyncConflictDto
{
    public function __construct(
        public string $eventId,
        public string $code,
        public string $message,
        public int $status,
        public string $category,
        public bool $retryable,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'eventId' => $this->eventId,
            'code' => $this->code,
            'message' => $this->message,
            'status' => $this->status,
            'category' => $this->category,
            'retryable' => $this->retryable,
        ];
    }
}
