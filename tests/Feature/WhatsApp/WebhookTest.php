<?php

use Addons\WhatsApp\Mail\SessionDisconnected;
use Addons\WhatsApp\Models\WhatsAppConversation;
use Addons\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Mail;
use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Addons\WhatsApp\Services\WhatsAppWebhookSignature;
use Webkul\Lead\Models\Lead;

/**
 * Runs against the real dev database (no RefreshDatabase in this project),
 * and `whatsapp_settings` is a singleton row shared by the whole install —
 * so every test captures the original settings and restores them in a
 * `finally`/afterEach rather than leaving the addon enabled with a test
 * secret behind.
 */
function whatsAppSettingsRepository()
{
    return app(WhatsAppSettingRepository::class);
}

/**
 * `WhatsAppSetting::$hidden` includes `api_key` and `webhook_secret` (so
 * they never leak into API responses) — which means `->toArray()` silently
 * *drops* those two fields entirely, regardless of any `Arr::only()` list
 * applied afterwards. A test that captured "the original settings" via
 * `->toArray()` could never actually restore them: the key just isn't
 * there to restore. Direct property access goes through the model's normal
 * accessors (correctly decrypting `encrypted`-cast fields) and bypasses
 * `$hidden` entirely, so this is the safe way to snapshot this row for a
 * test's later restore.
 */
function captureWhatsAppSettings(): array
{
    $settings = whatsAppSettingsRepository()->getSettings();

    return [
        'enabled' => $settings->enabled,
        'webhook_secret' => $settings->webhook_secret,
        'default_owner_id' => $settings->default_owner_id,
        'session_id' => $settings->session_id,
        'service_url' => $settings->service_url,
        'api_key' => $settings->api_key,
        'last_status' => $settings->last_status,
        'connected_number' => $settings->connected_number,
    ];
}

beforeEach(function () {
    $this->originalSettings = captureWhatsAppSettings();

    whatsAppSettingsRepository()->getSettings()->update([
        'enabled' => true,
        'webhook_secret' => 'test-webhook-secret',
        'default_owner_id' => getDefaultAdmin()->id,
    ]);
});

afterEach(function () {
    whatsAppSettingsRepository()->getSettings()->update($this->originalSettings);
});

function signedWhatsAppWebhookPost(array $payload)
{
    $rawBody = json_encode($payload);
    $signature = WhatsAppWebhookSignature::sign($rawBody, whatsAppSettingsRepository()->getSettings()->webhook_secret);

    return test()->call('POST', route('admin.whatsapp.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Webhook-Signature' => $signature,
    ], $rawBody);
}

it('rejects a webhook request with an invalid signature', function () {
    test()->call('POST', route('admin.whatsapp.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Webhook-Signature' => 'not-the-real-signature',
    ], json_encode(['type' => 'session.connected']))
        ->assertForbidden();
});

it('rejects any webhook request when the addon is disabled', function () {
    whatsAppSettingsRepository()->getSettings()->update(['enabled' => false]);

    signedWhatsAppWebhookPost(['type' => 'session.connected'])
        ->assertNotFound();
});

it('records a session.connected event on the settings row', function () {
    signedWhatsAppWebhookPost([
        'type' => 'session.connected',
        'session' => 'test',
        'number' => '5493511234567',
    ])->assertNoContent();

    $settings = whatsAppSettingsRepository()->getSettings()->fresh();

    expect($settings->last_status)->toBe('connected');
    expect($settings->connected_number)->toBe('5493511234567');
});

/**
 * A dropped session is silent: the panel looks fine, messages simply stop.
 * The alert is the only thing that surfaces it, and the de-duplication
 * matters as much as the alert — the microservice re-announces
 * `disconnected` on every reconnect attempt, and an alert that fires in a
 * loop is one people learn to ignore.
 */
it('alerts the lead-capture owner the first time a session drops, and not again', function () {
    Mail::fake();

    whatsAppSettingsRepository()->getSettings()->update(['last_status' => 'connected']);

    signedWhatsAppWebhookPost(['type' => 'session.disconnected'])->assertNoContent();

    Mail::assertSent(SessionDisconnected::class, 1);

    // Still disconnected: same state, no second alert.
    signedWhatsAppWebhookPost(['type' => 'session.disconnected'])->assertNoContent();

    Mail::assertSent(SessionDisconnected::class, 1);
});

it('creates a conversation, message and Lead from a first received message', function () {
    $waMessageId = 'test-msg-'.uniqid();
    // Deliberately not a real/matching number, so a new Person+Lead is created.
    $phone = '549999'.rand(1000, 9999);

    signedWhatsAppWebhookPost([
        'type' => 'message',
        'session' => 'test',
        'messageType' => 'received',
        'waMessageId' => $waMessageId,
        'remoteJid' => $phone.'@s.whatsapp.net',
        'number' => $phone,
        'text' => 'Hola, quiero información',
        'timestamp' => now()->timestamp,
    ])->assertNoContent();

    $message = WhatsAppMessage::where('wa_message_id', $waMessageId)->first();
    expect($message)->not->toBeNull();
    expect($message->type)->toBe('received');
    expect($message->body)->toBe('Hola, quiero información');

    $conversation = WhatsAppConversation::find($message->conversation_id);
    expect($conversation->phone_number)->toBe($phone);
    expect($conversation->lead_id)->not->toBeNull();

    $lead = Lead::find($conversation->lead_id);
    expect($lead)->not->toBeNull();
    expect($lead->user_id)->toBe(getDefaultAdmin()->id);

    // Retried delivery of the same message must not create a second row.
    signedWhatsAppWebhookPost([
        'type' => 'message',
        'session' => 'test',
        'messageType' => 'received',
        'waMessageId' => $waMessageId,
        'remoteJid' => $phone.'@s.whatsapp.net',
        'number' => $phone,
        'text' => 'Hola, quiero información',
        'timestamp' => now()->timestamp,
    ])->assertNoContent();

    expect(WhatsAppMessage::where('wa_message_id', $waMessageId)->count())->toBe(1);

    $lead->delete();
    $conversation->delete();
    $message->delete();
});

/**
 * The column carries no timezone, so the instant has to survive the
 * write/read round-trip. Inbound messages used to be stored as UTC wall
 * clock and read back as app-timezone, landing every received message the
 * app's UTC offset early (5h30m under Krayin's default Asia/Kolkata) while
 * CRM-sent ones were right — the two drifted apart inside one thread.
 */
it('stores an inbound message at the instant WhatsApp reported', function () {
    $waMessageId = 'test-tz-'.uniqid();
    $phone = '549555'.rand(1000, 9999);
    $timestamp = now()->subMinutes(3);

    signedWhatsAppWebhookPost([
        'type' => 'message',
        'session' => 'test',
        'messageType' => 'received',
        'waMessageId' => $waMessageId,
        'remoteJid' => $phone.'@s.whatsapp.net',
        'number' => $phone,
        'text' => 'Qué hora es',
        'timestamp' => $timestamp->timestamp,
    ])->assertNoContent();

    $message = WhatsAppMessage::where('wa_message_id', $waMessageId)->first();
    $conversation = WhatsAppConversation::find($message->conversation_id);

    // Cleanup in `finally`: a failing assertion would otherwise leave the
    // fixture Lead/Person behind in the real dev database.
    try {
        expect($message->sent_at->timestamp)->toBe($timestamp->timestamp);
    } finally {
        Lead::find($conversation->lead_id)?->delete();
        $conversation->delete();
        $message->delete();
    }
});

/**
 * A photo with no caption used to produce no text at all, and the
 * microservice dropped the whole message: no row, no Lead, no trace. A
 * customer could open a conversation with a single photo and the CRM would
 * never know. The file still isn't downloaded, but the message and the Lead
 * must exist.
 */
it('captures a media-only message and still creates its Lead', function () {
    $waMessageId = 'test-media-'.uniqid();
    $phone = '549666'.rand(1000, 9999);

    signedWhatsAppWebhookPost([
        'type' => 'message',
        'session' => 'test',
        'messageType' => 'received',
        'waMessageId' => $waMessageId,
        'remoteJid' => $phone.'@s.whatsapp.net',
        'number' => $phone,
        'text' => null,
        'mediaType' => 'image',
        'timestamp' => now()->timestamp,
    ])->assertNoContent();

    $message = WhatsAppMessage::where('wa_message_id', $waMessageId)->first();

    expect($message)->not->toBeNull();

    $conversation = WhatsAppConversation::find($message->conversation_id);

    try {
        expect($message->body)->toBeNull();
        expect($message->media_type)->toBe('image');
        expect($conversation->lead_id)->not->toBeNull();
    } finally {
        Lead::find($conversation->lead_id)?->delete();
        $conversation->delete();
        $message->delete();
    }
});

it('stores an echo message without treating it as a new inbound conversation trigger differently', function () {
    $waMessageId = 'test-msg-'.uniqid();
    $phone = '549888'.rand(1000, 9999);

    signedWhatsAppWebhookPost([
        'type' => 'message',
        'session' => 'test',
        'messageType' => 'echo',
        'waMessageId' => $waMessageId,
        'remoteJid' => $phone.'@s.whatsapp.net',
        'number' => $phone,
        'text' => 'Mensaje mandado desde el celular',
        'timestamp' => now()->timestamp,
    ])->assertNoContent();

    $message = WhatsAppMessage::where('wa_message_id', $waMessageId)->first();
    expect($message)->not->toBeNull();
    expect($message->type)->toBe('echo');

    $conversation = WhatsAppConversation::find($message->conversation_id);

    $lead = $conversation->lead_id ? Lead::find($conversation->lead_id) : null;
    $lead?->delete();
    $conversation->delete();
    $message->delete();
});
