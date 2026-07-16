<?php

namespace Tests\Unit\Services\DataIngestion;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Services\DataIngestion\ApiStructureDetector;
use App\Services\DataIngestion\HttpProbeService;
use App\Services\DataIngestion\LiveQuery\FilterCapabilityProbe;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiStructureDetectorTest extends TestCase
{
    private function detector(): ApiStructureDetector
    {
        $httpProbe = new HttpProbeService;
        $fetcher = new PaginatedApiFetcher($httpProbe);

        return new ApiStructureDetector($httpProbe, new FilterCapabilityProbe($httpProbe, $fetcher));
    }

    public function test_body_that_is_already_a_list_has_null_data_path_with_high_confidence(): void
    {
        Http::fake(['example.com/*' => Http::response([['id' => 1], ['id' => 2]])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('GET', $result['method']);
        $this->assertNull($result['data_path']);
        $this->assertSame('high', $result['data_path_confidence']);
    }

    public function test_single_top_level_candidate_is_detected_with_high_confidence(): void
    {
        Http::fake(['example.com/*' => Http::response(['data' => [['id' => 1]], 'count' => 1])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('data', $result['data_path']);
        $this->assertSame('high', $result['data_path_confidence']);
    }

    public function test_nested_envelope_one_level_deep_is_detected(): void
    {
        Http::fake(['example.com/*' => Http::response(['response' => ['results' => [['id' => 1]]]])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('response.results', $result['data_path']);
    }

    public function test_tie_between_two_candidates_is_broken_by_conventional_name(): void
    {
        Http::fake(['example.com/*' => Http::response([
            'meta' => [['some' => 'thing']],
            'results' => [['id' => 1]],
        ])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('results', $result['data_path']);
        $this->assertSame('medium', $result['data_path_confidence']);
    }

    public function test_no_record_array_anywhere_is_not_found(): void
    {
        Http::fake(['example.com/*' => Http::response(['status' => 'ok', 'total' => 0])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertNull($result['data_path']);
        $this->assertSame('not_found', $result['data_path_confidence']);
    }

    public function test_empty_list_is_kept_as_fallback_with_empty_response_confidence(): void
    {
        Http::fake(['example.com/*' => Http::response(['data' => []])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('data', $result['data_path']);
        $this->assertSame('empty_response', $result['data_path_confidence']);
    }

    public function test_get_failure_falls_back_to_post(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['message' => 'Method Not Allowed'], 405);
            }

            return Http::response(['data' => [['id' => 1]]]);
        });

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('POST', $result['method']);
        $this->assertSame('data', $result['data_path']);
    }

    public function test_get_and_post_both_failing_throws(): void
    {
        Http::fake(['example.com/*' => Http::response(['message' => 'nope'], 500)]);

        $this->expectException(ApiSourceFetchException::class);

        $this->detector()->detect('https://example.com/items');
    }

    public function test_next_link_pagination_detected_from_body(): void
    {
        Http::fake(['example.com/*' => Http::response([
            'data' => [['id' => 1]],
            'next_page_url' => 'https://example.com/items?page=2',
        ])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('next_link', $result['pagination']['style']);
        $this->assertSame('body', $result['pagination']['next_link_source']);
        $this->assertSame('guessed', $result['pagination_confidence']);
    }

    public function test_next_link_pagination_detected_from_header(): void
    {
        Http::fake(['example.com/*' => Http::response(
            ['data' => [['id' => 1]]],
            200,
            ['Link' => '<https://example.com/items?page=2>; rel="next"'],
        )]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('next_link', $result['pagination']['style']);
        $this->assertSame('header', $result['pagination']['next_link_source']);
    }

    public function test_cursor_pagination_detected_from_body_key(): void
    {
        Http::fake(['example.com/*' => Http::response([
            'data' => [['id' => 1]],
            'next_cursor' => 'abc123',
        ])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('cursor', $result['pagination']['style']);
        $this->assertSame('next_cursor', $result['pagination']['cursor_path']);
    }

    public function test_offset_pagination_confirmed_by_probing_size_param(): void
    {
        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            // Le premier candidat testé (size) est honoré : renvoyer exactement
            // autant d'enregistrements que demandé prouve que le paramètre marche.
            if (($query['size'] ?? null) === '5') {
                return Http::response(['data' => array_fill(0, 5, ['id' => 1]), 'total' => 50]);
            }

            return Http::response(['data' => [['id' => 1], ['id' => 2]], 'total' => 50]);
        });

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('page', $result['pagination']['style']);
        $this->assertSame('size', $result['pagination']['size_param']);
        $this->assertSame('confirmed', $result['pagination_confidence']);
    }

    public function test_no_pagination_signal_defaults_to_none(): void
    {
        Http::fake(['example.com/*' => Http::response(['data' => [['id' => 1], ['id' => 2]]])]);

        $result = $this->detector()->detect('https://example.com/items');

        $this->assertSame('none', $result['pagination']['style']);
        $this->assertSame('none', $result['pagination_confidence']);
    }
}
