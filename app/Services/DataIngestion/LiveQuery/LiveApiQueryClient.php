<?php

namespace App\Services\DataIngestion\LiveQuery;

use App\Domain\DataIngestion\Exceptions\LiveApiQueryException;
use App\Services\DataIngestion\HttpProbeService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

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
        } catch (RequestException $e) {
            if ($this->isEmptyResultStatus($e->response->status())) {
                return ['body' => [], 'headers' => [], 'status' => $e->response->status(), 'raw_size' => 0];
            }

            throw $this->translateFailure($e->response->status(), $e);
        } catch (ConnectionException $e) {
            throw new LiveApiQueryException(
                'La source externe met trop de temps à répondre ou est injoignable.',
                504,
                $e,
            );
        } catch (\RuntimeException $e) {
            // HttpProbeService::fetchPage lève un \RuntimeException générique sur JSON invalide
            // (le cas statut non-2xx est intercepté plus haut par RequestException — Http::retry()
            // lève cette exception avant même d'atteindre la vérification manuelle du statut).
            throw $this->translateFailure($this->extractHttpStatus($e->getMessage()), $e);
        }
    }

    /**
     * 404 ("aucun résultat") et 400 sont traités comme une page vide plutôt qu'une erreur : une
     * requête upstream mal formée du point de vue de l'API (valeur de filtre ou terme de
     * recherche que l'utilisateur a saisi et qu'elle rejette — accents, caractères non
     * supportés, terme trop court/trop large...) ne devrait pas afficher une erreur serveur
     * effrayante, juste "aucun résultat" — dégradation silencieuse, pas un crash.
     */
    private function isEmptyResultStatus(?int $status): bool
    {
        return $status === 404 || $status === 400;
    }

    private function translateFailure(?int $status, \Throwable $e): LiveApiQueryException
    {
        if ($status === 429) {
            return new LiveApiQueryException(
                'La source externe limite le nombre de requêtes pour le moment (429). Réessayez dans quelques instants.',
                429,
                $e,
            );
        }

        return new LiveApiQueryException(
            "La source externe est indisponible ou a retourné une erreur : {$e->getMessage()}",
            502,
            $e,
        );
    }

    /**
     * Variante parallèle de fetch() : exécute une requête par entrée de $queriesByKey
     * simultanément au lieu de les enchaîner l'une après l'autre — même contrat
     * d'erreur que fetch() (une requête en échec fait échouer tout le lot).
     *
     * @param  array<string, string>  $headers
     * @param  array<array-key, array<string, mixed>>  $queriesByKey
     * @return array<array-key, array{body: array, headers: array<string, string>, status: int, raw_size: int}>
     *
     * @throws LiveApiQueryException
     */
    public function fetchMany(string $url, string $method, array $headers, array $queriesByKey): array
    {
        $timeout = (int) config('statsio.data_ingestion.live_query.request_timeout_seconds', 25);

        try {
            return $this->httpProbe->fetchPages($url, $method, $headers, $queriesByKey, $timeout);
        } catch (ConnectionException $e) {
            throw new LiveApiQueryException(
                'La source externe met trop de temps à répondre ou est injoignable.',
                504,
                $e,
            );
        } catch (\RuntimeException $e) {
            throw $this->translateFailure($this->extractHttpStatus($e->getMessage()), $e);
        }
    }

    private function extractHttpStatus(string $message): ?int
    {
        return preg_match('/statut (\d{3})/', $message, $matches) === 1 ? (int) $matches[1] : null;
    }
}
