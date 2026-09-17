<?php

use Addons\Zadarma\Models\ZadarmaCallLog;
use Addons\Zadarma\Repositories\ZadarmaSettingRepository;
use Addons\Zadarma\Services\ZadarmaWebhookSignature;

/**
 * These tests run against the real dev database (no RefreshDatabase in this
 * project) and the settings row may already hold real Zadarma credentials,
 * so signatures are computed with the actually-saved api_secret rather than
 * a fixture value, and no request ever reaches the real Zadarma API.
 */
function zadarmaSettings()
{
    return app(ZadarmaSettingRepository::class)->getSettings();
}

it('echoes the zd_echo verification challenge', function () {
    $settings = zadarmaSettings();

    test()->get(route('admin.zadarma.webhook', $settings->webhook_secret).'?zd_echo=abc123')
        ->assertOK()
        ->assertSee('abc123', false);
});

it('rejects a request with the wrong webhook secret', function () {
    test()->post(route('admin.zadarma.webhook', 'not-the-real-secret'))
        ->assertNotFound();
});

it('rejects a NOTIFY_END event with an invalid signature', function () {
    $settings = zadarmaSettings();

    test()->post(route('admin.zadarma.webhook', $settings->webhook_secret), [
        'event'       => 'NOTIFY_END',
        'pbx_call_id' => 'test-call-'.uniqid(),
        'caller_id'   => '10000000000',
        'called_did'  => '20000000000',
        'call_start'  => now()->toDateTimeString(),
        'duration'    => 42,
        'disposition' => 'answered',
    ], ['Signature' => 'not-a-valid-signature'])
        ->assertForbidden();
});

it('accepts a correctly signed NOTIFY_END event and logs the call even with no matching lead', function () {
    $settings = zadarmaSettings();

    if (empty($settings->api_secret)) {
        test()->markTestSkipped('No api_secret configured yet, signature cannot be verified.');
    }

    $pbxCallId = 'test-call-'.uniqid();

    $payload = [
        'event'       => 'NOTIFY_END',
        'pbx_call_id' => $pbxCallId,
        // Deliberately not a real/matching number, so no Lead should match.
        'caller_id'   => '10000000000',
        'called_did'  => '20000000000',
        'call_start'  => now()->toDateTimeString(),
        'duration'    => 42,
        'disposition' => 'answered',
        'is_recorded' => 0,
    ];

    $signature = ZadarmaWebhookSignature::sign(
        ZadarmaWebhookSignature::incomingSignatureString($payload),
        $settings->api_secret
    );

    test()->post(route('admin.zadarma.webhook', $settings->webhook_secret), $payload, ['Signature' => $signature])
        ->assertNoContent();

    $log = ZadarmaCallLog::where('pbx_call_id', $pbxCallId)->first();

    expect($log)->not->toBeNull();
    expect($log->lead_id)->toBeNull();
    expect($log->duration)->toBe(42);
    expect($log->disposition)->toBe('answered');

    // Retried delivery of the same call must not create a second log row.
    test()->post(route('admin.zadarma.webhook', $settings->webhook_secret), $payload, ['Signature' => $signature])
        ->assertNoContent();

    expect(ZadarmaCallLog::where('pbx_call_id', $pbxCallId)->count())->toBe(1);

    $log->delete();
});
