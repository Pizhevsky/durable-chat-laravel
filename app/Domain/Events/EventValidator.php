<?php

namespace App\Domain\Events;

use App\Domain\Shared\DomainRuleException;

final readonly class EventValidator
{
    private const EVENT_ID_PATTERN = '/^[^:\s]+:[^:\s]+$/';

    private const ISO_DATE_TIME_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/';

    public function __construct(private EventPayloadValidator $payloadValidator) {}

    public function validate(ChatEventDto $event): void
    {
        if (! preg_match(self::EVENT_ID_PATTERN, $event->eventId)) {
            throw $this->invalid('eventId must be in originDeviceId:eventId format.');
        }

        if ($event->logicalClock < 0) {
            throw $this->invalid('logicalClock must be a non-negative integer.');
        }

        if (! preg_match(self::ISO_DATE_TIME_PATTERN, $event->createdAt) || strtotime($event->createdAt) === false) {
            throw $this->invalid('createdAt must be an ISO 8601 date string.');
        }

        $this->payloadValidator->validate($event);
    }

    private function invalid(string $message): DomainRuleException
    {
        return new DomainRuleException($message, 422, 'INVALID_EVENT');
    }
}
