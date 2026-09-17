<?php

use Addons\WhatsApp\Models\WhatsAppConversation;
use Addons\WhatsApp\Models\WhatsAppMessage;
use Webkul\Lead\Models\Lead;

/**
 * Same conventions as WebhookTest.php: real dev database, capture/restore
 * the singleton settings row, and use the already-proven webhook flow to
 * create a real conversation+Lead fixture instead of hand-rolling one.
 */
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

function createChatFixtureConversation(): WhatsAppConversation
{
    $waMessageId = 'chat-test-msg-'.uniqid();
    $phone = '549777'.rand(1000, 9999);

    signedWhatsAppWebhookPost([
        'type' => 'message',
        'session' => 'test',
        'messageType' => 'received',
        'waMessageId' => $waMessageId,
        'remoteJid' => $phone.'@s.whatsapp.net',
        'number' => $phone,
        'text' => 'Fixture message for ChatController tests',
        'timestamp' => now()->timestamp,
    ])->assertNoContent();

    return WhatsAppMessage::where('wa_message_id', $waMessageId)->first()->conversation;
}

function deleteChatFixture(WhatsAppConversation $conversation): void
{
    if ($conversation->lead_id) {
        Lead::find($conversation->lead_id)?->delete();
    }

    WhatsAppMessage::where('conversation_id', $conversation->id)->delete();
    $conversation->delete();
}

it('returns an empty message list for a lead with no conversation yet', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->getJson(route('admin.whatsapp.messages.index', 999999999))
        ->assertOK()
        ->assertJson(['messages' => []]);
});

it('lists the message history for a lead that has a conversation', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();

    test()->actingAs($admin)
        ->getJson(route('admin.whatsapp.messages.index', $conversation->lead_id))
        ->assertOK()
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.type', 'received')
        ->assertJsonPath('messages.0.body', 'Fixture message for ChatController tests');

    deleteChatFixture($conversation);
});

it('rejects sending a message to a lead with no conversation', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->postJson(route('admin.whatsapp.messages.store', 999999999), ['message' => 'hi'])
        ->assertStatus(422)
        ->assertJson(['message' => trans('whatsapp::app.chat.no-conversation')]);
});

it('rejects sending a message when the microservice connection is not configured', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();

    // Explicitly blanked rather than assumed — this shared settings row may
    // already have real connection details saved (e.g. from live testing).
    whatsAppSettingsRepository()->getSettings()->update([
        'service_url' => null,
        'session_id' => null,
        'api_key' => null,
    ]);

    test()->actingAs($admin)
        ->postJson(route('admin.whatsapp.messages.store', $conversation->lead_id), ['message' => 'hola'])
        ->assertStatus(422)
        ->assertJson(['message' => trans('whatsapp::app.chat.not-configured')]);

    deleteChatFixture($conversation);
});

it('surfaces a friendly error when the microservice is unreachable, without crashing', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();

    whatsAppSettingsRepository()->getSettings()->update([
        // Reserved/unused local port — fails fast with "connection refused"
        // instead of hanging, so this test stays quick and hits no real service.
        'service_url' => 'http://127.0.0.1:1',
        'session_id' => 'test',
        'api_key' => 'test-key',
    ]);

    test()->actingAs($admin)
        ->postJson(route('admin.whatsapp.messages.store', $conversation->lead_id), ['message' => 'hola'])
        ->assertStatus(422);

    // No message row should have been written for a send that never succeeded.
    expect(WhatsAppMessage::where('conversation_id', $conversation->id)->where('type', 'sent_api')->count())->toBe(0);

    deleteChatFixture($conversation);
});
