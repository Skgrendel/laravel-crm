<?php

use Addons\Zadarma\Http\Controllers\SettingsController;
use Addons\Zadarma\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/zadarma')->group(function () {
    Route::controller(SettingsController::class)->group(function () {
        Route::get('', 'index')->name('admin.settings.zadarma.index');

        Route::put('', 'update')->name('admin.settings.zadarma.update');

        Route::post('webhook-secret/regenerate', 'regenerateWebhookSecret')->name('admin.settings.zadarma.webhook_secret.regenerate');

        Route::post('test-connection', 'testConnection')->name('admin.settings.zadarma.test_connection');
    });
});

/**
 * Public webhook endpoint Zadarma calls directly — no admin session, so
 * the `user` auth middleware (still applied to the rest of this group) is
 * removed here. Guarded instead by the `{secret}` path segment and, for the
 * actual event payloads, Zadarma's own `Signature` header.
 */
Route::any('zadarma/webhook/{secret}', [WebhookController::class, 'handle'])
    ->name('admin.zadarma.webhook')
    ->withoutMiddleware('user');
