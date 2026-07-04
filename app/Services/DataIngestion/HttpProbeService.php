<?php

namespace App\Services\DataIngestion;

use Illuminate\Support\Facades\Http;

class HttpProbeService
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Calls an external URL and returns the decoded JSON body.
     *
     * @param  array<string, string>  $headers
     *
     * @throws \RuntimeException if the request fails or the response isn't JSON.
     */
    public function fetch(string $url, string $method, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(self::TIMEOUT_SECONDS)
            ->send(strtoupper($method), $url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "La requête a échoué avec le statut {$response->status()}."
            );
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new \RuntimeException('La réponse ne contient pas de JSON valide.');
        }

        return $decoded;
    }

    /**
     * Lightweight connectivity check used by the "Tester la connexion" button —
     * only verifies the endpoint answers with a non-error status.
     */
    public function probe(string $url, string $method, array $headers = []): void
    {
        $response = Http::withHeaders($headers)
            ->timeout(self::TIMEOUT_SECONDS)
            ->send(strtoupper($method), $url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "La requête a échoué avec le statut {$response->status()}."
            );
        }
    }
}
