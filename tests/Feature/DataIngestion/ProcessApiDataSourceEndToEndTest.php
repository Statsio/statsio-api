<?php

namespace Tests\Feature\DataIngestion;

use App\Domain\DataIngestion\Actions\FetchApiDataSourcePagesAction;
use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use App\Services\DataIngestion\DataIngestionOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exécute la pipeline complète d'une source API en mode snapshot sans passer par la
 * queue (FetchApiDataSourcePagesAction puis DataIngestionOrchestrator directement,
 * comme le ferait ProcessDataSourceJob::handle()) — vérifie que le nouveau chemin
 * streamé (pages -> raw.jsonl -> JsonLinesParser -> DuckDbParquetWriter) produit bien
 * un Parquet réel et cohérent, colonnes comprises, avec un dataset assez gros pour ne
 * pas tenir "par accident" dans un seul buffer.
 */
class ProcessApiDataSourceEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_streamed_api_pipeline_produces_a_ready_dataset_with_correct_rows_and_columns(): void
    {
        Storage::fake();

        $pageSize = 50;
        $pageCount = 4; // 200 lignes réparties sur plusieurs pages, colonnes qui varient
        $sequence = Http::sequence();

        for ($page = 0; $page < $pageCount; $page++) {
            $records = [];
            for ($i = 0; $i < $pageSize; $i++) {
                $id = $page * $pageSize + $i + 1;
                $record = ['id' => $id, 'label' => "item-{$id}", 'active' => $id % 2 === 0];
                if ($page === $pageCount - 1) {
                    // colonne apparaissant seulement sur la dernière page : doit quand
                    // même finir dans l'union des headers (comme JsonParser::fromRecords).
                    $record['note'] = "note-{$id}";
                }
                $records[] = $record;
            }
            $sequence->push(['data' => $records]);
        }
        $sequence->push(['data' => []]); // page finale vide : signal de fin de pagination "offset"

        Http::fake(['example.com/*' => $sequence]);

        $user = User::factory()->create();
        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => 'E2E API source',
            'type' => DataSourceTypeEnum::JSON,
            'source_kind' => 'api',
            'api_config' => [
                'url' => 'https://example.com/items',
                'method' => 'GET',
                'auth_type' => 'none',
                'headers' => [],
                'data_path' => 'data',
                'pagination' => [
                    'style' => 'offset', 'param_name' => 'offset', 'param_start' => 0,
                    'size_param' => 'limit', 'page_size' => $pageSize,
                ],
            ],
            'original_filename' => 'E2E API source.json',
            'raw_storage_path' => null,
            'file_size_bytes' => 0,
            'status' => 'pending',
        ]);

        \App\Models\DataIngestion\Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $dataSource->user_id,
            'name' => $dataSource->name,
            'status' => 'pending',
            'row_count' => 0,
        ]);

        app(FetchApiDataSourcePagesAction::class)->execute($dataSource);
        $dataSource->refresh();

        $this->assertStringEndsWith('.jsonl', $dataSource->raw_storage_path);

        $dataset = app(DataIngestionOrchestrator::class)->process($dataSource);

        $expectedRows = $pageSize * $pageCount;

        $this->assertSame('ready', $dataset->status->value);
        $this->assertSame($expectedRows, $dataset->row_count);
        $this->assertNotNull($dataset->parquet_path);
        Storage::assertExists($dataset->parquet_path);

        $columnNames = $dataset->fresh()->columns->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['id', 'label', 'active', 'note'], $columnNames);

        // Lecture réelle du Parquet produit via DuckDB, pour vérifier que le fichier
        // n'est pas seulement "présent" mais correct (row count, valeurs).
        $absoluteParquetPath = Storage::path($dataset->parquet_path);
        $escaped = escapeshellarg($absoluteParquetPath);
        $count = (int) trim(shell_exec("duckdb -csv -noheader -c \"SELECT COUNT(*) FROM read_parquet({$escaped})\" 2>/dev/null"));
        $this->assertSame($expectedRows, $count);

        $firstNote = trim(shell_exec("duckdb -csv -noheader -c \"SELECT note FROM read_parquet({$escaped}) WHERE id = 1\" 2>/dev/null"));
        $this->assertSame('', $firstNote); // colonne absente pour cette ligne -> vide, pas d'erreur

        $lastNote = trim(shell_exec("duckdb -csv -noheader -c \"SELECT note FROM read_parquet({$escaped}) WHERE id = {$expectedRows}\" 2>/dev/null"));
        $this->assertSame("note-{$expectedRows}", $lastNote);
    }
}
