<?php

use Addons\WhatsApp\Models\WhatsAppConversation;
use Addons\WhatsApp\Models\WhatsAppMessage;
use Addons\WhatsApp\Repositories\WhatsAppSettingRepository;
use Addons\WhatsApp\Services\WhatsAppWebhookSignature;
use Illuminate\Support\Arr;
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

beforeEach(function () {
    $this->originalSettings = whatsAppSettingsRepository()->getSettings()->toArray();

    whatsAppSettingsRepository()->getSettings()->update([
        'enabled' => true,
        'webhook_secret' => 'test-webhook-secret',
        'default_owner_id' => getDefaultAdmin()->id,
    ]);
});

afterEach(function () {
    whatsAppSettingsRepository()->getSettings()->update(Arr::only($this->originalSettings, [
        'enabled', 'webhook_secret', 'default_owner_id', 'session_id', 'service_url', 'last_status', 'connected_number',
    ]));
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
