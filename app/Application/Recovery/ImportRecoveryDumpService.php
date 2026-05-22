<?php

namespace App\Application\Recovery;

use App\Application\Sync\SyncEventsResultDto;
use App\Application\Sync\SyncEventsService;
use App\Domain\Shared\DomainRuleException;

final readonly class ImportRecoveryDumpService
{
    public function __construct(private SyncEventsService $syncEvents) {}

    /** @param array<string, mixed> $dump */
    public function import(array $dump): SyncEventsResultDto
    {
        if (($dump['format'] ?? null) !== config('durable-chat.recovery_format')) {
            throw new DomainRuleException('Unsupported recovery dump format.', 422, 'UNSUPPORTED_RECOVERY_FORMAT');
        }

        if (! is_array($dump['events'] ?? null)) {
            throw new DomainRuleException('Recovery dump must contain an events array.', 422, 'INVALID_RECOVERY_DUMP');
        }

        if (isset($dump['checksum'])) {
            $expectedChecksum = hash('sha256', json_encode($dump['events'], JSON_THROW_ON_ERROR));
            if ($dump['checksum'] !== $expectedChecksum) {
                throw new DomainRuleException('Recovery dump checksum mismatch.', 422, 'CHECKSUM_MISMATCH');
            }
        }

        return $this->syncEvents->sync($dump['events'], (string) ($dump['deviceId'] ?? 'recovery-import'));
    }
}
