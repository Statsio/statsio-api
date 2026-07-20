<?php

namespace App\Services\Medicaments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Relais vers l'API REST de Wikipédia (résumé de page), utilisé pour illustrer les médicaments
 * grand public : aucune source officielle (BDPM, Open Medic, OMS, openFDA) ne fournit de photo
 * de conditionnement. Couverture partielle par construction (seuls les médicaments avec un
 * article Wikipédia ont une image) — dégrade vers null sans faire échouer la page, le frontend
 * affiche une icône générique par forme pharmaceutique dans ce cas.
 */
class WikipediaImageClient
{
    private const CACHE_TTL_DAYS = 30;

    public function getThumbnailUrl(string $name): ?string
    {
        $key = 'wikipedia-image:'.md5(mb_strtolower(trim($name)));

        return Cache::remember($key, now()->addDays(self::CACHE_TTL_DAYS), function () use ($name) {
            // MediaWiki ne capitalise automatiquement que la 1ère lettre d'un titre — "DOLIPRANE"
            // (tel que fourni par la BDPM, toujours en MAJUSCULES) ne matche donc jamais l'article
            // réel "Doliprane" et 404 systématiquement. On normalise en casse titre avant requête.
            $normalized = mb_convert_case(mb_strtolower(trim($name)), MB_CASE_TITLE, 'UTF-8');

            try {
                // Wikimedia rejette (403) toute requête sans User-Agent identifiable, cf.
                // https://w.wiki/4wJS — un UA générique ou absent échoue silencieusement sinon.
                $response = Http::baseUrl('https://fr.wikipedia.org/api/rest_v1')
                    ->withHeaders(['User-Agent' => 'Statsio/1.0 (https://statsio.fr; contact@statsio.fr) Laravel-HTTP-Client'])
                    ->timeout(5)
                    ->get('/page/summary/'.rawurlencode($normalized));
            } catch (ConnectionException) {
                return null;
            }

            if (! $response->successful()) {
                return null;
            }

            return $response->json('thumbnail.source');
        });
    }
}
