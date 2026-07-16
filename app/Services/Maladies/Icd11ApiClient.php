<?php

namespace App\Services\Maladies;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Relais vers l'API ICD-11 de l'OMS (icd.who.int/icdapi) — référentiel de classification
 * (définitions, synonymes, hiérarchie) des maladies, en remplacement de la Disease Ontology.
 * Authentification OAuth2 client-credentials (jeton ~1h, mis en cache). Contrairement à la
 * Disease Ontology, l'API sert nativement le français via l'en-tête Accept-Language, ce qui
 * supprime le besoin d'un complément Wikidata pour les libellés FR.
 *
 * On identifie une maladie par son id de linéarisation numérique (ex. 119724091), pas par son
 * code clinique (ex. 5A11) : la recherche ICD-11 (/mms/search) ne renvoie que cet id sans appel
 * supplémentaire par résultat, alors que résoudre le code passerait par un aller-retour
 * /mms/codeinfo/{code} en plus. Le code clinique reste affiché (champ `code` de la réponse),
 * simplement pas utilisé comme identifiant d'URL.
 */
class Icd11ApiClient
{
    private const TOKEN_CACHE_KEY = 'icd11:token';

    private const RELEASE_CACHE_KEY = 'icd11:release-id';

    /** @return array{id: string, code: string|null, title: string}[] */
    public function search(string $query, string $language = 'fr'): array
    {
        $response = $this->client($language)
            ->get($this->mmsPath().'/search', [
                'q' => $query,
                'flatResults' => 'true',
            ])
            ->throw()
            ->json();

        return collect($response['destinationEntities'] ?? [])
            ->filter(fn (array $entity) => ! empty($entity['id']))
            ->map(fn (array $entity) => [
                'id' => $this->extractId($entity['id']),
                'title' => strip_tags($entity['title'] ?? ''),
            ])
            // Certaines entités de linéarisation sont post-coordonnées (ex. ".../1217915084/unspecified"),
            // un sous-chemin à plusieurs segments qu'une route Laravel à un seul paramètre ne peut
            // pas adresser proprement. On les exclut des résultats de recherche — ce sont des
            // variantes post-coordination d'une entité de base, pas des maladies distinctes à
            // parcourir individuellement.
            ->filter(fn (array $entity) => ! str_contains($entity['id'], '/'))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: string, code: string|null, title: string|null, definition: string|null,
     *     synonyms: string[], inclusions: string[], classKind: string|null,
     *     parentIds: string[], childIds: string[],
     * }|null
     */
    public function getById(string $id, string $language = 'fr'): ?array
    {
        return Cache::remember(
            "icd11:entity:{$id}:{$language}",
            now()->addDay(),
            function () use ($id, $language) {
                $response = $this->client($language)->get($this->mmsPath()."/{$id}");

                if ($response->status() === 404) {
                    return null;
                }

                $data = $response->throw()->json();

                return [
                    'id' => $id,
                    'code' => ($data['code'] ?? '') !== '' ? $data['code'] : null,
                    'title' => $data['title']['@value'] ?? null,
                    'definition' => $data['definition']['@value'] ?? null,
                    'synonyms' => collect($data['indexTerm'] ?? [])
                        ->pluck('label.@value')->filter()->values()->all(),
                    'inclusions' => collect($data['inclusion'] ?? [])
                        ->pluck('label.@value')->filter()->values()->all(),
                    'classKind' => $data['classKind'] ?? null,
                    'parentIds' => collect($data['parent'] ?? [])
                        ->map(fn (string $uri) => $this->extractId($uri))->values()->all(),
                    'childIds' => collect($data['child'] ?? [])
                        ->map(fn (string $uri) => $this->extractId($uri))->values()->all(),
                ];
            },
        );
    }

    /** Titre seul d'un ancestor (bloc/chapitre), pour la ligne de fil d'Ariane de la fiche. */
    public function getTitle(string $id, string $language = 'fr'): ?string
    {
        return $this->getById($id, $language)['title'] ?? null;
    }

    /** Release ICD-11 effectivement utilisée (ex. "2024-01"), pour l'affichage de provenance. */
    public function currentReleaseId(): string
    {
        return $this->releaseId();
    }

    /**
     * Tout ce qui suit ".../mms/" dans l'URI — pas juste le dernier segment, car certaines
     * entités post-coordonnées ont un id à plusieurs segments (ex. ".../mms/123/unspecified").
     */
    private function extractId(string $uri): string
    {
        return (string) Str::after(rtrim($uri, '/'), '/mms/');
    }

    private function client(string $language)
    {
        return Http::baseUrl(config('services.icd11_api.base_url'))
            ->withToken($this->token())
            ->withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => $language,
                'API-Version' => 'v2',
            ])
            ->timeout(10);
    }

    private function mmsPath(): string
    {
        return "/icd/release/11/{$this->releaseId()}/mms";
    }

    /** Résout la release courante (ex. "2024-01") : "latest" n'est pas un segment d'URL valide. */
    private function releaseId(): string
    {
        $configured = config('services.icd11_api.release_id');

        if ($configured && $configured !== 'latest') {
            return $configured;
        }

        return Cache::remember(self::RELEASE_CACHE_KEY, now()->addDay(), function () {
            $root = Http::baseUrl(config('services.icd11_api.base_url'))
                ->withToken($this->token())
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en',
                    'API-Version' => 'v2',
                ])
                ->timeout(10)
                ->get('/icd/entity')
                ->throw()
                ->json();

            return $root['releaseId'];
        });
    }

    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(55), function () {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.icd11_api.client_id'),
                    config('services.icd11_api.client_secret'),
                )
                ->post(config('services.icd11_api.token_url'), [
                    'grant_type' => 'client_credentials',
                    'scope' => 'icdapi_access',
                ])
                ->throw()
                ->json();

            return $response['access_token'];
        });
    }
}
