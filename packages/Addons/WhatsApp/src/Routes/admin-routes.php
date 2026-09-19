<?php

use Addons\WhatsApp\Http\Controllers\ChatController;
use Addons\WhatsApp\Http\Controllers\InboxController;
use Addons\WhatsApp\Http\Controllers\SessionController;
use Addons\WhatsApp\Http\Controllers\SettingsController;
use Addons\WhatsApp\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/whatsapp')->group(function () {
    Route::controller(SettingsController::class)->group(function () {
        Route::get('', 'index')->name('admin.settings.whatsapp.index');

        Route::put('', 'update')->name('admin.settings.whatsapp.update');

        Route::post('api-key/regenerate', 'regenerateApiKey')->name('admin.settings.whatsapp.api_key.regenerate');

        Route::post('webhook-secret/regenerate', 'regenerateWebhookSecret')->name('admin.settings.whatsapp.webhook_secret.regenerate');
    });

    Route::controller(SessionController::class)->prefix('session')->group(function () {
        Route::get('status', 'status')->name('admin.settings.whatsapp.session.status');

        Route::get('qr', 'qr')->name('admin.settings.whatsapp.session.qr');

        Route::post('reconnect', 'reconnect')->name('admin.settings.whatsapp.session.reconnect');

        Route::post('logout', 'logout')->name('admin.settings.whatsapp.session.logout');
    });
});

/**
 * Unified inbox (Fase 3.2) — conversations across every Lead, ordered by
 * who has been waiting longest.
 */
Route::controller(InboxController::class)->prefix('whatsapp/inbox')->group(function () {
    Route::get('', 'index')->name('admin.whatsapp.inbox.index');

    Route::get('list', 'list')->name('admin.whatsapp.inbox.list');

    Route::post('{conversationId}/read', 'markRead')->name('admin.whatsapp.inbox.read');

    Route::post('{conversationId}/claim', 'claim')->name('admin.whatsapp.inbox.claim');
});

/**
 * Chat panel (Fase 2.3), nested under the Lead it belongs to.
 */
Route::controller(ChatController::class)->prefix('leads/{leadId}/whatsapp/messages')->group(function () {
    Route::get('', 'index')->name('admin.whatsapp.messages.index');

    Route::post('', 'store')->name('admin.whatsapp.messages.store');

    Route::get('{messageId}/media', 'media')->name('admin.whatsapp.messages.media');
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
    ->withoutMiddleware('user')
    /**
     * Throttled because this endpoint is reachable by anyone: the signature
     * check rejects forgeries, but only *after* an HMAC over the whole body
     * and a settings lookup, so unlimited requests are free CPU and database
     * load for an attacker. The ceiling is far above what one WhatsApp
     * session produces — a busy sales line is a few messages a minute.
     */
    ->middleware('throttle:120,1');
