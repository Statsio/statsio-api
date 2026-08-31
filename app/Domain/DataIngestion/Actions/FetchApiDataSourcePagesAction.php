<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Models\DataIngestion\DataSource;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Récupère l'intégralité des pages d'une source API (boucle complète, potentiellement
 * plusieurs requêtes HTTP) et écrit le résultat agrégé dans `raw.jsonl` — appelée
 * uniquement depuis ProcessDataSourceJob, jamais depuis un contrôleur synchrone.
 *
 * Chaque page reçue est écrite directement sur disque (une ligne JSON par
 * enregistrement) via le callback `onPage` de PaginatedApiFetcher::fetchAll() —
 * aucun tableau PHP contenant l'ensemble des enregistrements n'est jamais
 * matérialisé, ce qui évite l'explosion mémoire sur les APIs à centaines de
 * milliers de lignes. DataIngestionOrchestrator lit ensuite ce fichier via
 * JsonLinesParser, lui aussi streamant.
 */
class FetchApiDataSourcePagesAction
{
    public function __construct(
        private readonly PaginatedApiFetcher $fetcher,
    ) {}

    /**
     * @throws ApiSourceFetchException
     */
    public function execute(DataSource $dataSource): void
    {
        $config = $dataSource->api_config ?? [];
        $url = $config['url'] ?? null;

        if (! $url) {
            throw new ApiSourceFetchException("L'URL de la source est requise.");
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'statsio_raw_').'.jsonl';
        $handle = fopen($tempPath, 'w');
        if ($handle === false) {
            throw new ApiSourceFetchException('Impossible de créer le fichier temporaire de récupération.');
        }

        $pageCount = 0;

        try {
            $result = $this->fetcher->fetchAll(
                url: $url,
                method: $config['method'] ?? 'GET',
                headers: $config['headers'] ?? [],
                dataPath: $config['data_path'] ?? null,
                pagination: $config['pagination'] ?? ['style' => 'none'],
                onPage: function (array $records) use ($handle, $dataSource, &$pageCount) {
                    foreach ($records as $record) {
                        fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE)."\n");
                    }
                    // Le nombre total de pages est inconnu à l'avance (dépend du style de
                    // pagination) — on plafonne à 20% pour laisser la marge au pipeline de
                    // l'orchestrator (parse/schema/parquet/upload/persist) qui suit.
                    $pageCount++;
                    $dataSource->dataset?->updateProgress(min(5 + $pageCount * 2, 20));
                },
            );
        } finally {
            fclose($handle);
        }

        $storagePath = 'private/datasources/'.Str::uuid().'/raw.jsonl';
        $fileSize = filesize($tempPath) ?: 0;

        $stream = fopen($tempPath, 'r');
        Storage::put($storagePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        unlink($tempPath);

        $dataSource->update([
            'raw_storage_path' => $storagePath,
            'file_size_bytes' => $fileSize,
            'is_partial' => $result['truncated'],
            'partial_reason' => $result['stopped_reason'],
        ]);
    }
}
