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

    public function test_fetch_returns_decoded_json_body(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true, 'items' => [1, 2, 3]])]);

        $result = (new HttpProbeService())->fetch('https://example.com/items', 'GET');

        $this->assertSame(['ok' => true, 'items' => [1, 2, 3]], $result);
    }

    public function test_fetch_throws_when_response_failed(): void
    {
        Http::fake(['example.com/*' => Http::response([], 500)]);

        $this->expectException(\RuntimeException::class);

        (new HttpProbeService())->fetch('https://example.com/items', 'GET');
    }

    public function test_fetch_throws_when_body_is_not_json_array(): void
    {
        Http::fake(['example.com/*' => Http::response('not json', 200, ['Content-Type' => 'text/plain'])]);

        $this->expectException(\RuntimeException::class);

        (new HttpProbeService())->fetch('https://example.com/items', 'GET');
    }

    public function test_probe_throws_when_response_failed(): void
    {
        Http::fake(['example.com/*' => Http::response([], 503)]);

        $this->expectException(\RuntimeException::class);

        (new HttpProbeService())->probe('https://example.com/items', 'GET');
    }

    public function test_probe_does_not_throw_on_success(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true])]);

        (new HttpProbeService())->probe('https://example.com/items', 'GET');

        Http::assertSentCount(1);
    }

    public function test_fetch_pages_runs_one_request_per_key_in_parallel(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['data' => [$query['q'] ?? null]]);
        });

        $results = (new HttpProbeService())->fetchPages(
            'https://example.com/items',
            'GET',
            [],
            ['first' => ['q' => 'alpha'], 'second' => ['q' => 'beta']],
            15,
        );

        $this->assertSame(['alpha'], $results['first']['body']['data']);
        $this->assertSame(['beta'], $results['second']['body']['data']);
    }

    public function test_fetch_pages_treats_404_as_an_empty_page(): void
    {
        Http::fake(['example.com/*' => Http::response([], 404)]);

        $results = (new HttpProbeService())->fetchPages(
            'https://example.com/items', 'GET', [], ['only' => []], 15,
        );

        $this->assertSame([], $results['only']['body']);
        $this->assertSame(404, $results['only']['status']);
    }

    public function test_fetch_pages_throws_on_server_error(): void
    {
        Http::fake(['example.com/*' => Http::response([], 500)]);

        $this->expectException(\RuntimeException::class);

        (new HttpProbeService())->fetchPages('https://example.com/items', 'GET', [], ['only' => []], 15);
    }
}
