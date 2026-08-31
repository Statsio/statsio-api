<?php

namespace App\Services\Identity;

use App\Domain\Identity\Exceptions\DiditException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Relais vers l'API Didit v3 (https://verification.didit.me) — création de sessions
 * de vérification d'identité (KYC) et lecture de la décision.
 *
 * Authentification par en-tête `x-api-key`. Entièrement testable via Http::fake().
 *
 * @see https://docs.didit.me/sessions-api/create-session
 */
class DiditApiClient
{
    /** Le module est actif si la clé API + le workflow sont renseignés, ou en mode dev simulé. */
    public function isConfigured(): bool
    {
        return $this->isFake()
            || (filled(config('services.didit.api_key')) && filled(config('services.didit.workflow_id')));
    }

    /**
     * Mode simulé (`DIDIT_FAKE=true`) : la vérification est approuvée immédiatement, sans
     * appel réseau ni compte Didit. Jamais actif en production.
     */
    public function isFake(): bool
    {
        return config('services.didit.fake') === true && ! app()->isProduction();
    }

    /**
     * Crée une session et renvoie l'essentiel pour rediriger l'utilisateur.
     *
     * @return array{session_id: string, session_number: int|null, url: string, status: string}
     */
    public function createSession(string $vendorData, string $callbackUrl): array
    {
        try {
            $payload = $this->client()
                ->post('/v3/session/', [
                    'workflow_id' => config('services.didit.workflow_id'),
                    'vendor_data' => $vendorData,
                    'callback' => $callbackUrl,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException $e) {
            throw DiditException::sessionCreationFailed($e->getMessage());
        } catch (RequestException $e) {
            throw DiditException::sessionCreationFailed((string) $e->response?->body());
        }

        if (! is_array($payload) || empty($payload['session_id']) || empty($payload['url'])) {
            throw DiditException::sessionCreationFailed('réponse Didit inattendue.');
        }

        return [
            'session_id' => (string) $payload['session_id'],
            'session_number' => isset($payload['session_number']) ? (int) $payload['session_number'] : null,
            'url' => (string) $payload['url'],
            'status' => (string) ($payload['status'] ?? 'Not Started'),
        ];
    }

    /**
     * Décision complète d'une session — repli quand un webhook a été manqué.
     *
     * @return array<string, mixed>|null
     */
    public function getSessionDecision(string $sessionId): ?array
    {
        try {
            $response = $this->client()->get("/v3/session/{$sessionId}/decision/");
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.didit.base_url'), '/'))
            ->withHeaders([
                'x-api-key' => (string) config('services.didit.api_key'),
                'Accept' => 'application/json',
            ])
            ->timeout(15);
    }
}
