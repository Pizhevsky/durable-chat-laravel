<?php

namespace App\Application\Events;

use App\Domain\Events\ChatEventDto;

final readonly class ApplyEventResultDto
{
    public function __construct(
        public ChatEventDto $event,
        public bool $inserted,
    ) {}
}
