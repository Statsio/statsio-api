<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Throwable;

class TurnstileToken implements ValidationRule
{
    public function __construct(
        private readonly string $action,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret');

        // Pas de secret configuré (local, CI) : on n'impose pas la vérification plutôt que de
        // bloquer tous les environnements qui n'ont pas encore le secret (fourni via la pipeline
        // de déploiement, cf. TURNSTILE_SECRET dans .env.example).
        if (! $secret) {
            return;
        }

        if (! is_string($value) || $value === '' || strlen($value) > 2048) {
            $fail('La vérification anti-robot a échoué. Merci de réessayer.');

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ]);
        } catch (Throwable) {
            $fail('La vérification anti-robot a échoué. Merci de réessayer.');

            return;
        }

        if (! $response->successful()) {
            $fail('La vérification anti-robot a échoué. Merci de réessayer.');

            return;
        }

        $result = $response->json();

        $expectedHostname = parse_url((string) config('app.frontend_url'), PHP_URL_HOST);

        $hostnameMatches = ! $expectedHostname || ($result['hostname'] ?? null) === $expectedHostname;

        if (! ($result['success'] ?? false) || ($result['action'] ?? null) !== $this->action || ! $hostnameMatches) {
            $fail('La vérification anti-robot a échoué. Merci de réessayer.');
        }
    }
}
