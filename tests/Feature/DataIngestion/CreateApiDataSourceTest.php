<?php

namespace Tests\Feature\DataIngestion;

use App\Jobs\ProcessDataSourceJob;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateApiDataSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_pending_source_with_a_single_validation_call(): void
    {
        Queue::fake();
        Http::fake(['example.com/*' => Http::response(['data' => [1, 2, 3]])]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/api-sources', [
            'name' => 'Hub\'eau qualité eau',
            'url' => 'https://example.com/items',
            'method' => 'GET',
            'data_path' => 'data',
            'pagination' => [
                'style' => 'next_link',
                'next_link_source' => 'body',
                'next_link_path' => 'next',
                'max_pages' => 50,
            ],
        ]);

        $response->assertStatus(202)->assertJsonPath('success', true);

        Http::assertSentCount(1);

        $dataSource = DataSource::first();
        $this->assertSame('api', $dataSource->source_kind);
        $this->assertSame('pending', $dataSource->status->value);
        $this->assertNull($dataSource->raw_storage_path);
        $this->assertSame('next_link', $dataSource->api_config['pagination']['style']);
        $this->assertSame(50, $dataSource->api_config['pagination']['max_pages']);

        Queue::assertPushed(ProcessDataSourceJob::class);
    }

    public function test_rejects_invalid_pagination_style(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/api-sources', [
            'name' => 'Invalid source',
            'url' => 'https://example.com/items',
            'pagination' => ['style' => 'not_a_real_style'],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('pagination.style');
    }
}
