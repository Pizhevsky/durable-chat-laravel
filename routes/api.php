<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\VerifyHelperSignature;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/readiness', ReadinessController::class);
Route::get('/config', ConfigController::class);
Route::get('/users', [UserController::class, 'index']);
Route::get('/chats', [ChatController::class, 'index']);
Route::get('/chats/{chatId}/messages', [MessageController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::middleware(VerifyHelperSignature::class)->group(function (): void {
    Route::post('/sync/events', [SyncController::class, 'store']);
    Route::get('/sync/events', [SyncController::class, 'index']);
    Route::get('/recovery/export', [RecoveryController::class, 'export']);
    Route::post('/recovery/import', [RecoveryController::class, 'import']);
});
