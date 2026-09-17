<?php

use Addons\WhatsApp\Http\Controllers\SettingsController;
use Addons\WhatsApp\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/whatsapp')->group(function () {
    Route::controller(SettingsController::class)->group(function () {
        Route::get('', 'index')->name('admin.settings.whatsapp.index');

        Route::put('', 'update')->name('admin.settings.whatsapp.update');
    });
});

/**
 * Public webhook endpoint the baileys-whatsapp-service microservice calls
 * directly — no admin session, so the `user` auth middleware (still applied
 * to the rest of this group) is removed here. Guarded by the
 * `X-Webhook-Signature` header (see WebhookController) instead of a secret
 * URL segment, matching the contract documented in that repo's README.
 */
Route::post('whatsapp/webhook', [WebhookController::class, 'handle'])
    ->name('admin.whatsapp.webhook')
    ->withoutMiddleware('user');
