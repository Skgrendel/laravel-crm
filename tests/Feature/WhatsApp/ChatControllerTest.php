<?php

use Addons\WhatsApp\Models\WhatsAppConversation;
use Addons\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

it('returns who the agent is talking to alongside the history', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();

    try {
        $contact = test()->actingAs($admin)
            ->getJson(route('admin.whatsapp.messages.index', $conversation->lead_id))
            ->assertOK()
            ->json('contact');

        expect($contact['phone'])->toBe($conversation->phone_number);
        expect($contact['name'])->not->toBeEmpty();
        // A plain @s.whatsapp.net contact has a real, dialable number.
        expect($contact['is_hidden_number'])->toBeFalse();
    } finally {
        deleteChatFixture($conversation);
    }
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

it('sends an attachment, keeps a copy, and serves it back only through the lead it belongs to', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();

    whatsAppSettingsRepository()->getSettings()->update([
        'service_url' => 'http://microservice.test',
        'session_id'  => 'test',
        'api_key'     => 'test-key',
    ]);

    Http::fake([
        '*/send-media*' => Http::response(['waMessageId' => 'wa-media-'.uniqid()]),
    ]);

    Storage::fake();

    try {
        $response = test()->actingAs($admin)->postJson(
            route('admin.whatsapp.messages.store', $conversation->lead_id),
            [
                'message'    => 'Te mando el contrato',
                'attachment' => UploadedFile::fake()->create('contrato.pdf', 12, 'application/pdf'),
            ]
        )->assertOK();

        $message = WhatsAppMessage::find($response->json('message.id'));

        expect($message->media_type)->toBe('document');
        expect($message->media_name)->toBe('contrato.pdf');
        expect($message->body)->toBe('Te mando el contrato');

        // The copy the CRM keeps: WhatsApp is not an archive, and the
        // document trail belongs to the Lead.
        Storage::assertExists($message->media_path);

        // The file travels as the raw body, not multipart or base64.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/send-media')
                && str_contains($request->url(), 'fileName=contrato.pdf');
        });

        test()->actingAs($admin)
            ->get(route('admin.whatsapp.messages.media', [$conversation->lead_id, $message->id]))
            ->assertOK()
            ->assertDownload('contrato.pdf');

        // Same message id, wrong Lead: the file must not come back.
        test()->actingAs($admin)
            ->get(route('admin.whatsapp.messages.media', [999999999, $message->id]))
            ->assertNotFound();
    } finally {
        deleteChatFixture($conversation);
    }
});

it('rejects a send with neither text nor an attachment', function () {
    $admin = getDefaultAdmin();
    $conversation = createChatFixtureConversation();

    try {
        test()->actingAs($admin)
            ->postJson(route('admin.whatsapp.messages.store', $conversation->lead_id), [])
            ->assertStatus(422);
    } finally {
        deleteChatFixture($conversation);
    }
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
