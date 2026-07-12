<?php

namespace Tests\Unit\Domain\DataIngestion;

use App\Services\DataIngestion\HttpProbeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpProbeServiceTest extends TestCase
{
    /**
     * Régression : Guzzle remplace (et ne fusionne pas) la query string de l'URI avec
     * l'option 'query'. Passer 'query' => [] écrasait silencieusement une query string
     * déjà présente dans l'URL (ex. "?size=5000" saisi à la main pour une source en
     * pagination 'next_link', qui ne construit pas de $query elle-même) — l'appel
     * partait alors sans aucune taille de page, ce qui fait timeout sur une grosse API.
     */
    public function test_fetch_page_preserves_query_string_already_in_the_url_when_query_is_empty(): void
    {
        Http::fake(['example.com/*' => Http::response(['data' => [1, 2]])]);

        (new HttpProbeService())->fetchPage('https://example.com/items?size=5000', 'GET', [], [], 15);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/items?size=5000');
    }

    public function test_fetch_page_still_applies_explicit_query_params(): void
    {
        Http::fake(['example.com/*' => Http::response(['data' => [1, 2]])]);

        (new HttpProbeService())->fetchPage('https://example.com/items', 'GET', [], ['page' => 2, 'limit' => 10], 15);

        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/items?page=2&limit=10');
    }
}
