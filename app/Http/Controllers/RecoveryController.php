<?php

namespace App\Http\Controllers;

use App\Application\Recovery\ExportRecoveryDumpService;
use App\Application\Recovery\ImportRecoveryDumpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class RecoveryController
{
    public function __construct(
        private ExportRecoveryDumpService $exportRecoveryDump,
        private ImportRecoveryDumpService $importRecoveryDump,
    ) {}

    public function export(Request $request): JsonResponse
    {
        $userId = (string) $request->query('userId', 'unknown');
        $deviceId = (string) $request->query('deviceId', 'laravel-central-export');
        $dump = $this->exportRecoveryDump->export($userId, $deviceId);

        return response()
            ->json($dump)
            ->header('Content-Disposition', 'attachment; filename="chat-recovery-laravel-central.json"');
    }

    public function import(Request $request): JsonResponse
    {
        $result = $this->importRecoveryDump->import($request->json()->all());

        return response()->json($result->toResponseArray());
    }
}
