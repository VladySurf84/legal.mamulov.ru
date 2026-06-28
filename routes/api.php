<?php

use App\Http\Controllers\Api\Internal\SignatureSyncController;
use App\Http\Controllers\Api\HhBrowserCaptureController;
use App\Http\Controllers\Api\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('telegram/webhook/{secret}', TelegramWebhookController::class)
    ->name('api.telegram.webhook');

Route::post('hh/browser-captures', [HhBrowserCaptureController::class, 'store'])
    ->middleware('hh.browser.capture')
    ->name('api.hh.browser-captures.store');
Route::post('hh/browser-captures/lookup', [HhBrowserCaptureController::class, 'lookup'])
    ->middleware('hh.browser.capture')
    ->name('api.hh.browser-captures.lookup');

Route::prefix('internal')
    ->middleware('internal.api')
    ->group(function (): void {
        Route::get('signatures', [SignatureSyncController::class, 'index'])
            ->name('api.internal.signatures.index');
        Route::post('signatures/import', [SignatureSyncController::class, 'import'])
            ->name('api.internal.signatures.import');
        Route::get('signatures/{signature}', [SignatureSyncController::class, 'show'])
            ->name('api.internal.signatures.show');
        Route::post('signatures/{signature}/sign', [SignatureSyncController::class, 'sign'])
            ->name('api.internal.signatures.sign');
    });
