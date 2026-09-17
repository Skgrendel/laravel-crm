<?php

/**
 * Same capture/restore convention as the other WhatsApp test files — see
 * whatsAppSettingsRepository() and captureWhatsAppSettings() in
 * WebhookTest.php ($hidden fields need direct property access, not toArray()).
 */
beforeEach(function () {
    $this->originalSettings = captureWhatsAppSettings();
});

afterEach(function () {
    whatsAppSettingsRepository()->getSettings()->update($this->originalSettings);
});

it('shows the whatsapp settings page including the session status component', function () {
    $admin = getDefaultAdmin();

    test()->actingAs($admin)
        ->get(route('admin.settings.whatsapp.index'))
        ->assertOK()
        ->assertSee('v-whatsapp-session', false);
});

it('rejects checking session status when the microservice connection is not configured', function () {
    $admin = getDefaultAdmin();

    whatsAppSettingsRepository()->getSettings()->update([
        'session_id' => null,
        'service_url' => null,
        'api_key' => null,
    ]);

    test()->actingAs($admin)
        ->getJson(route('admin.settings.whatsapp.session.status'))
        ->assertStatus(422);
});

it('rejects fetching a QR when the microservice connection is not configured', function () {
    $admin = getDefaultAdmin();

    whatsAppSettingsRepository()->getSettings()->update([
        'session_id' => null,
        'service_url' => null,
        'api_key' => null,
    ]);

    test()->actingAs($admin)
        ->getJson(route('admin.settings.whatsapp.session.qr'))
        ->assertStatus(422);
});

it('surfaces a friendly error checking status when the microservice is unreachable', function () {
    $admin = getDefaultAdmin();

    whatsAppSettingsRepository()->getSettings()->update([
        // Reserved/unused local port — fails fast, no real network call.
        'service_url' => 'http://127.0.0.1:1',
        'session_id' => 'test',
        'api_key' => 'test-key',
    ]);

    test()->actingAs($admin)
        ->getJson(route('admin.settings.whatsapp.session.status'))
        ->assertStatus(422);
});
