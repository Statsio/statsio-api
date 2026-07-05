<?php

namespace Tests\Unit\Domain\DataIngestion;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Services\DataIngestion\HttpProbeService;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaginatedApiFetcherTest extends TestCase
{
    private function fetcher(): PaginatedApiFetcher
    {
        return new PaginatedApiFetcher(new HttpProbeService);
    }

    public function test_fetch_first_page_returns_single_page_records(): void
    {
        Http::fake(['example.com/*' => Http::response(['data' => [1, 2, 3]])]);

        $result = $this->fetcher()->fetchFirstPage('https://example.com/items', 'GET', [], 'data', ['style' => 'none']);

        $this->assertSame([1, 2, 3], $result['records']);
        Http::assertSentCount(1);
    }

    public function test_fetch_all_aggregates_offset_pages_until_short_page(): void
    {
        Http::fake(['example.com/*' => Http::sequence()
            ->push(['data' => [1, 2, 3]])
            ->push(['data' => [4]]),
        ]);

        $pagination = ['style' => 'offset', 'param_name' => 'offset', 'param_start' => 0, 'size_param' => 'limit', 'page_size' => 3];
        $result = $this->fetcher()->fetchAll('https://example.com/items', 'GET', [], 'data', $pagination);

        $this->assertSame([1, 2, 3, 4], $result['records']);
        $this->assertFalse($result['truncated']);
        $this->assertNull($result['stopped_reason']);
        $this->assertSame(2, $result['pages_fetched']);
        Http::assertSentCount(2);
    }

    public function test_fetch_all_aggregates_cursor_pages_until_null_cursor(): void
    {
        Http::fake(['example.com/*' => Http::sequence()
            ->push(['data' => [1, 2], 'next_cursor' => 'abc'])
            ->push(['data' => [3], 'next_cursor' => null]),
        ]);

        $pagination = ['style' => 'cursor', 'cursor_param' => 'cursor', 'cursor_path' => 'next_cursor', 'size_param' => 'limit', 'page_size' => 2];
        $result = $this->fetcher()->fetchAll('https://example.com/items', 'GET', [], 'data', $pagination);

        $this->assertSame([1, 2, 3], $result['records']);
        $this->assertFalse($result['truncated']);
        $this->assertSame(2, $result['pages_fetched']);
    }

    public function test_fetch_all_follows_next_link_in_body(): void
    {
        Http::fake([
            'https://example.com/page1' => Http::response(['data' => [1], 'next' => 'https://example.com/page2']),
            'https://example.com/page2' => Http::response(['data' => [2]]),
        ]);

        $pagination = ['style' => 'next_link', 'next_link_source' => 'body', 'next_link_path' => 'next'];
        $result = $this->fetcher()->fetchAll('https://example.com/page1', 'GET', [], 'data', $pagination);

        $this->assertSame([1, 2], $result['records']);
        $this->assertSame(2, $result['pages_fetched']);
    }

    public function test_fetch_all_follows_next_link_in_header(): void
    {
        Http::fake([
            'https://example.com/page1' => Http::response(['data' => [1]], 200, ['Link' => '<https://example.com/page2>; rel="next"']),
            'https://example.com/page2' => Http::response(['data' => [2]]),
        ]);

        $pagination = ['style' => 'next_link', 'next_link_source' => 'header'];
        $result = $this->fetcher()->fetchAll('https://example.com/page1', 'GET', [], 'data', $pagination);

        $this->assertSame([1, 2], $result['records']);
        $this->assertSame(2, $result['pages_fetched']);
    }

    public function test_fetch_all_stops_at_max_pages_and_marks_truncated(): void
    {
        Http::fake(['example.com/*' => Http::sequence()
            ->push(['data' => [1]])
            ->push(['data' => [2]])
            ->push(['data' => [3]]),
        ]);

        $pagination = ['style' => 'offset', 'param_name' => 'offset', 'page_size' => 1, 'max_pages' => 2];
        $result = $this->fetcher()->fetchAll('https://example.com/items', 'GET', [], 'data', $pagination);

        $this->assertSame([1, 2], $result['records']);
        $this->assertTrue($result['truncated']);
        $this->assertSame('max_pages', $result['stopped_reason']);
        $this->assertSame(2, $result['pages_fetched']);
        Http::assertSentCount(2);
    }

    public function test_fetch_all_stops_at_max_rows_and_marks_truncated(): void
    {
        config(['statsio.data_ingestion.max_rows' => 2]);

        Http::fake(['example.com/*' => Http::sequence()
            ->push(['data' => [1, 2]])
            ->push(['data' => [3, 4]]),
        ]);

        $pagination = ['style' => 'offset', 'param_name' => 'offset', 'page_size' => 2];
        $result = $this->fetcher()->fetchAll('https://example.com/items', 'GET', [], 'data', $pagination);

        $this->assertSame([1, 2], $result['records']);
        $this->assertTrue($result['truncated']);
        $this->assertSame('max_rows', $result['stopped_reason']);
        $this->assertSame(1, $result['pages_fetched']);
        Http::assertSentCount(1);
    }

    public function test_fetch_all_throws_when_response_exceeds_size_guard(): void
    {
        config(['statsio.data_ingestion.pagination.max_response_bytes_per_page' => 5]);

        Http::fake(['example.com/*' => Http::response(['data' => [1, 2, 3, 4, 5, 6, 7, 8]])]);

        $this->expectException(ApiSourceFetchException::class);

        $this->fetcher()->fetchAll('https://example.com/items', 'GET', [], 'data', ['style' => 'none']);
    }

    public function test_fetch_first_page_throws_when_data_path_is_not_a_list(): void
    {
        Http::fake(['example.com/*' => Http::response(['data' => ['not' => 'a list']])]);

        $this->expectException(ApiSourceFetchException::class);

        $this->fetcher()->fetchFirstPage('https://example.com/items', 'GET', [], 'data', ['style' => 'none']);
    }
}
