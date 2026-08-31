<?php

namespace App\Domain\Identity\Support;

/**
 * Vérifie la signature d'un webhook Didit.
 *
 * En-tête `X-Signature` = HMAC-SHA256 des octets bruts du corps, clé = Webhook
 * Secret Key. En-tête `X-Timestamp` (epoch secondes) rejeté au-delà de 5 min pour
 * contrer le rejeu.
 *
 * @see https://docs.didit.me/integration/webhooks
 */
class DiditWebhookSignature
{
    private const TOLERANCE_SECONDS = 300;

    public static function isValid(string $rawBody, ?string $signature, ?string $timestamp): bool
    {
        $secret = (string) config('services.didit.webhook_secret');

        if ($secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        if (! is_string($timestamp) || ! ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
