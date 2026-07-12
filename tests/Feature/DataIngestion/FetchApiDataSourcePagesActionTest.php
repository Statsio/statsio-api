<?php

namespace Tests\Feature\DataIngestion;

use App\Domain\DataIngestion\Actions\FetchApiDataSourcePagesAction;
use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FetchApiDataSourcePagesActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeApiDataSource(array $pagination): DataSource
    {
        $user = User::factory()->create();

        return DataSource::create([
            'user_id' => $user->id,
            'name' => 'Test API source',
            'type' => DataSourceTypeEnum::JSON,
            'source_kind' => 'api',
            'api_config' => [
                'url' => 'https://example.com/items',
                'method' => 'GET',
                'auth_type' => 'none',
                'headers' => [],
                'data_path' => 'data',
                'pagination' => $pagination,
            ],
            'original_filename' => 'Test API source.json',
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'status' => 'pending',
        ]);
    }

    /** @return array<int, mixed> */
    private function decodeJsonLines(string $content): array
    {
        $lines = array_filter(explode("\n", trim($content)), fn (string $line) => $line !== '');

        return array_values(array_map(fn (string $line) => json_decode($line, true), $lines));
    }

    public function test_execute_streams_all_pages_into_raw_jsonl(): void
    {
        Storage::fake();
        Http::fake(['example.com/*' => Http::sequence()
            ->push(['data' => [1, 2]])
            ->push(['data' => [3]]),
        ]);

        $dataSource = $this->makeApiDataSource([
            'style' => 'offset', 'param_name' => 'offset', 'param_start' => 0,
            'size_param' => 'limit', 'page_size' => 2,
        ]);

        app(FetchApiDataSourcePagesAction::class)->execute($dataSource);
        $dataSource->refresh();

        $this->assertNotNull($dataSource->raw_storage_path);
        $this->assertStringEndsWith('.jsonl', $dataSource->raw_storage_path);
        Storage::assertExists($dataSource->raw_storage_path);
        $this->assertSame([1, 2, 3], $this->decodeJsonLines(Storage::get($dataSource->raw_storage_path)));
        $this->assertFalse($dataSource->is_partial);
        $this->assertNull($dataSource->partial_reason);
        $this->assertGreaterThan(0, $dataSource->file_size_bytes);
    }

    public function test_execute_marks_source_partial_when_max_pages_reached(): void
    {
        Storage::fake();
        Http::fake(['example.com/*' => Http::sequence()
            ->push(['data' => [1]])
            ->push(['data' => [2]])
            ->push(['data' => [3]]),
        ]);

        $dataSource = $this->makeApiDataSource([
            'style' => 'offset', 'param_name' => 'offset', 'page_size' => 1, 'max_pages' => 2,
        ]);

        app(FetchApiDataSourcePagesAction::class)->execute($dataSource);
        $dataSource->refresh();

        $this->assertSame([1, 2], $this->decodeJsonLines(Storage::get($dataSource->raw_storage_path)));
        $this->assertTrue($dataSource->is_partial);
        $this->assertSame('max_pages', $dataSource->partial_reason);
    }
}
