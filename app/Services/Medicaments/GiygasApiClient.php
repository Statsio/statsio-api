<?php

namespace App\Services\Medicaments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Relais vers l'API publique medicaments-api.giygas.dev (données BDPM).
 * Aucune authentification requise côté amont, mais le quota est partagé par IP
 * (1000 tokens, recharge 3/s) — comme toutes les requêtes visiteurs transitent
 * désormais par l'IP du serveur, chaque réponse est mise en cache.
 */
class GiygasApiClient
{
    public function search(string $query): array
    {
        $term = $this->sanitizeSearchTerm($query);

        return Cache::remember(
            "medicaments-api:search:{$term}",
            now()->addMinutes(15),
            fn () => $this->client()->get('/v1/medicaments', ['search' => $term])->throw()->json(),
        );
    }

    public function generiques(string $libelle): array
    {
        $term = $this->sanitizeSearchTerm($libelle);

        return Cache::remember(
            "medicaments-api:generiques:{$term}",
            now()->addMinutes(15),
            fn () => $this->client()->get('/v1/generiques', ['libelle' => $term])->throw()->json(),
        );
    }

    public function getByCis(int $cis): ?array
    {
        return Cache::remember(
            "medicaments-api:cis:{$cis}",
            now()->addMinutes(15),
            function () use ($cis) {
                $response = $this->client()->get("/v1/medicaments/{$cis}");

                if ($response->status() === 404) {
                    return null;
                }

                return $response->throw()->json();
            },
        );
    }

    private function client()
    {
        return Http::baseUrl(config('services.medicaments_api.base_url'))->timeout(10);
    }

    /**
     * medicaments-api.giygas.dev rejette tout terme de recherche contenant des accents
     * ou des caractères hors de [A-Za-z0-9 .\/'+-] : on translittère en ASCII plutôt que
     * de laisser échouer une recherche française toute simple ("énergisant" -> "energisant"),
     * comme documenté dans LiveDatasetQueryService::sanitizeSearchTerm().
     */
    private function sanitizeSearchTerm(string $term): string
    {
        $ascii = Str::ascii($term);
        $stripped = preg_replace("/[^A-Za-z0-9 .\/'+-]+/", ' ', $ascii) ?? '';

        return trim(preg_replace('/\s+/', ' ', $stripped) ?? '');
    }
}
