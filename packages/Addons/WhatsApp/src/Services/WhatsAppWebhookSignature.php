<?php

namespace Addons\WhatsApp\Services;

/**
 * Verifies the `X-Webhook-Signature` header the baileys-whatsapp-service
 * microservice sends on every webhook request: HMAC-SHA256 of the raw JSON
 * request body, hex-encoded, using the session's `webhook_secret` — see that
 * repo's README for the full contract. Mirrors the verification pattern
 * already used for Zadarma's webhook, but HMAC-SHA256/hex since this secret
 * is our own design, not dictated by a third-party API.
 */
class WhatsAppWebhookSignature
{
    public static function sign(string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $rawBody, $secret);
    }

    /**
     * Verify a received signature against the expected one, using a
     * timing-safe comparison.
     */
    public static function verify(string $rawBody, string $receivedSignature, string $secret): bool
    {
        if (empty($receivedSignature)) {
            return false;
        }

        return hash_equals(static::sign($rawBody, $secret), $receivedSignature);
    }
}
