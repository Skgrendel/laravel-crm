<?php

namespace Addons\Zadarma\Services;

/**
 * Verifies the `Signature` header Zadarma sends on webhook requests
 * (NOTIFY_START, NOTIFY_END, NOTIFY_OUT_END, ...).
 *
 * Reverse-engineered from Zadarma's official PHP SDK (zadarma/user-api-v1):
 * `Client::encodeSignature()` is `base64(hmac_sha1($signatureString, $secret))`,
 * where `$secret` is the account's API secret key (the same one used to sign
 * outgoing API requests — Zadarma does not have a separate "webhook secret").
 * `$signatureString` is built per event type by concatenating specific
 * fields from the payload with no separator, e.g. NotifyStart/NotifyEnd use
 * `caller_id . called_did . call_start`, while NotifyOutEnd uses
 * `internal . destination . call_start`.
 */
class ZadarmaWebhookSignature
{
    /**
     * Build the signature string for NOTIFY_START / NOTIFY_END (incoming calls).
     */
    public static function incomingSignatureString(array $payload): string
    {
        return ($payload['caller_id'] ?? '').($payload['called_did'] ?? '').($payload['call_start'] ?? '');
    }

    /**
     * Build the signature string for NOTIFY_OUT_START / NOTIFY_OUT_END (outgoing calls).
     */
    public static function outgoingSignatureString(array $payload): string
    {
        return ($payload['internal'] ?? '').($payload['destination'] ?? '').($payload['call_start'] ?? '');
    }

    /**
     * Compute the expected signature for a given string and secret.
     */
    public static function sign(string $signatureString, string $secret): string
    {
        return base64_encode(hash_hmac('sha1', $signatureString, $secret));
    }

    /**
     * Verify a received signature against the expected one, using a
     * timing-safe comparison.
     */
    public static function verify(string $signatureString, string $receivedSignature, string $secret): bool
    {
        return hash_equals(static::sign($signatureString, $secret), $receivedSignature);
    }
}
