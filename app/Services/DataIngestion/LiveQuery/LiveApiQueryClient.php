<?php

namespace App\Services\DataIngestion\LiveQuery;

use App\Domain\DataIngestion\Exceptions\LiveApiQueryException;
use App\Services\DataIngestion\HttpProbeService;
use Illuminate\Http\Client\ConnectionException;

/**
 * Enveloppe fine autour de HttpProbeService::fetchPage() pour le chemin de
 * requêtage live : timeout dédié (les pages Hub'Eau mesurées ~20s nécessitent
 * un budget plus large que la validation de connexion) et erreurs upstream
 * traduites en LiveApiQueryException (jamais une exception brute qui ferait
 * planter la requête Studio).
 */
class LiveApiQueryClient
{
    public function __construct(
        private readonly HttpProbeService $httpProbe,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $query
     * @return array{body: array, headers: array<string, string>, status: int, raw_size: int}
     *
     * @throws LiveApiQueryException
     */
    public function fetch(string $url, string $method, array $headers, array $query): array
    {
        $timeout = (int) config('statsio.data_ingestion.live_query.request_timeout_seconds', 25);

        try {
            return $this->httpProbe->fetchPage($url, $method, $headers, $query, $timeout);
        } catch (ConnectionException $e) {
            throw new LiveApiQueryException(
                'La source externe met trop de temps à répondre ou est injoignable.',
                504,
                $e,
            );
        } catch (\RuntimeException $e) {
            // HttpProbeService::fetchPage lève un \RuntimeException générique sur statut non-2xx ou JSON invalide.
            if ($this->extractHttpStatus($e->getMessage()) === 429) {
                throw new LiveApiQueryException(
                    'La source externe limite le nombre de requêtes pour le moment (429). Réessayez dans quelques instants.',
                    429,
                    $e,
                );
            }

            throw new LiveApiQueryException(
                "La source externe est indisponible ou a retourné une erreur : {$e->getMessage()}",
                502,
                $e,
            );
        }
    }

    private function extractHttpStatus(string $message): ?int
    {
        return preg_match('/statut (\d{3})/', $message, $matches) === 1 ? (int) $matches[1] : null;
    }
}
