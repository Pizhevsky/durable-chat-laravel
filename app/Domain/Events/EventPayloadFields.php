<?php

namespace App\Domain\Events;

use App\Domain\Shared\DomainRuleException;

final class EventPayloadFields
{
    public function requireString(mixed $value, string $fieldName): void
    {
        if (! is_string($value) || trim($value) === '') {
            throw $this->invalid("{$fieldName} must be a non-empty string.");
        }
    }

    public function requireChatType(mixed $value): string
    {
        if ($value !== 'direct' && $value !== 'group') {
            throw $this->invalid('chat.created payload type must be direct or group.');
        }

        return $value;
    }

    /** @return array<int, string> */
    public function requireStringArray(mixed $value, string $fieldName): array
    {
        if (! is_array($value) || $value === []) {
            throw $this->invalid('chat.created memberIds must be a non-empty string array.');
        }

        foreach ($value as $item) {
            $this->requireString($item, $fieldName);
        }

        return array_values($value);
    }

    /** @param array<string, mixed> $payload */
    public function validateOptionalString(array $payload, string $fieldName, ?int $maxLength = null): void
    {
        if (! array_key_exists($fieldName, $payload) || $payload[$fieldName] === null) {
            return;
        }

        if (! is_string($payload[$fieldName]) || ($maxLength !== null && mb_strlen($payload[$fieldName]) > $maxLength)) {
            $limit = $maxLength === null ? '' : ' no longer than '.$maxLength.' characters';
            throw $this->invalid("{$fieldName} must be a string{$limit}.");
        }
    }

    public function invalid(string $message): DomainRuleException
    {
        return new DomainRuleException($message, 422, 'INVALID_EVENT');
    }
}
