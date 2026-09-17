<?php

use Addons\Zadarma\Http\Controllers\ExtensionMappingController;
use Addons\Zadarma\Http\Controllers\PhoneController;
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

    Route::put('extensions', [ExtensionMappingController::class, 'update'])->name('admin.settings.zadarma.extensions.update');
});

/**
 * Softphone popup — deliberately outside the `settings/zadarma` prefix and
 * rendered without the admin shell (see PhoneController::show docblock).
 */
Route::controller(PhoneController::class)->prefix('zadarma/phone')->group(function () {
    Route::get('', 'show')->name('admin.zadarma.phone.show');

    Route::get('webrtc-key', 'webrtcKey')->name('admin.zadarma.phone.webrtc_key');
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
