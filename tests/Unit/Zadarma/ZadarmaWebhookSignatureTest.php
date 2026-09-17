<?php

use Addons\Zadarma\Services\ZadarmaWebhookSignature;

it('builds the incoming call signature string from caller_id, called_did and call_start', function () {
    $string = ZadarmaWebhookSignature::incomingSignatureString([
        'caller_id'  => '79261112233',
        'called_did' => '74951112233',
        'call_start' => '2026-09-17 10:00:00',
        'ignored'    => 'should not appear',
    ]);

    expect($string)->toBe('7926111223374951112233'.'2026-09-17 10:00:00');
});

it('builds the outgoing call signature string from internal, destination and call_start', function () {
    $string = ZadarmaWebhookSignature::outgoingSignatureString([
        'internal'    => '101',
        'destination' => '79261112233',
        'call_start'  => '2026-09-17 10:00:00',
    ]);

    expect($string)->toBe('101'.'79261112233'.'2026-09-17 10:00:00');
});

it('signs and verifies matching signatures', function () {
    $signature = ZadarmaWebhookSignature::sign('some-string', 'secret-key');

    expect(ZadarmaWebhookSignature::verify('some-string', $signature, 'secret-key'))->toBeTrue();
});

it('rejects a signature computed with the wrong secret', function () {
    $signature = ZadarmaWebhookSignature::sign('some-string', 'secret-key');

    expect(ZadarmaWebhookSignature::verify('some-string', $signature, 'wrong-key'))->toBeFalse();
});

it('rejects a signature computed for a different string', function () {
    $signature = ZadarmaWebhookSignature::sign('some-string', 'secret-key');

    expect(ZadarmaWebhookSignature::verify('another-string', $signature, 'secret-key'))->toBeFalse();
});
