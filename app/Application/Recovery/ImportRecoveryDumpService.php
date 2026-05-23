<?php

namespace App\Application\Recovery;

use App\Application\Sync\SyncConflictMapper;
use App\Application\Sync\SyncEventsResultDto;
use App\Application\Sync\SyncEventsService;
use App\Domain\Shared\DomainRuleException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ImportRecoveryDumpService
{
    public function __construct(
        private SyncEventsService $syncEvents,
        private SyncConflictMapper $conflictMapper,
        private RecoveryChecksum $checksum,
    ) {}

    /** @param array<string, mixed> $dump */
    public function import(array $dump, bool $dryRun = false): SyncEventsResultDto
    {
        $this->assertValidDumpShape($dump);

        /** @var array<int, array<string, mixed>> $eventRows */
        $eventRows = $dump['events'];

        if (! $dryRun) {
            return $this->syncEvents->sync($eventRows);
        }

        return $this->previewWithRollback($eventRows);
    }

    /** @param array<string, mixed> $dump */
    private function assertValidDumpShape(array $dump): void
    {
        if (($dump['format'] ?? null) !== config('durable-chat.recovery_format')) {
            throw new DomainRuleException('Unsupported recovery dump format.', 422, 'UNSUPPORTED_RECOVERY_FORMAT');
        }

        if (! is_array($dump['events'] ?? null)) {
            throw new DomainRuleException('Recovery dump must contain an events array.', 422, 'INVALID_RECOVERY_DUMP');
        }

        if (! is_string($dump['checksum'] ?? null) || trim($dump['checksum']) === '') {
            throw new DomainRuleException('Recovery dump must contain a SHA-256 checksum.', 422, 'MISSING_RECOVERY_CHECKSUM');
        }

        if (! hash_equals($dump['checksum'], $this->checksum->forEvents($dump['events']))) {
            throw new DomainRuleException('Recovery dump checksum does not match the events payload.', 422, 'RECOVERY_CHECKSUM_MISMATCH');
        }
    }

    /** @param array<int, array<string, mixed>> $eventRows */
    private function previewWithRollback(array $eventRows): SyncEventsResultDto
    {
        DB::beginTransaction();

        try {
            $result = $this->syncEvents->sync($eventRows);
            DB::rollBack();

            return new SyncEventsResultDto(
                $result->accepted,
                $result->duplicates,
                $result->conflicts,
                $result->serverEvents,
                true,
            );
        } catch (Throwable $error) {
            DB::rollBack();

            return new SyncEventsResultDto(
                [],
                [],
                [$this->conflictMapper->fromThrowable('recovery-dump', $error, 'RECOVERY_PREVIEW_ERROR')],
                [],
                true,
            );
        }
    }
}
