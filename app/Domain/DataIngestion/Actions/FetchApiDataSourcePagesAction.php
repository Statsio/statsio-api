<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Models\DataIngestion\DataSource;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Récupère l'intégralité des pages d'une source API (boucle complète, potentiellement
 * plusieurs requêtes HTTP) et écrit le résultat agrégé dans `raw.json` — appelée
 * uniquement depuis ProcessDataSourceJob, jamais depuis un contrôleur synchrone.
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

        $result = $this->fetcher->fetchAll(
            url: $url,
            method: $config['method'] ?? 'GET',
            headers: $config['headers'] ?? [],
            dataPath: $config['data_path'] ?? null,
            pagination: $config['pagination'] ?? ['style' => 'none'],
        );

        $storagePath = 'private/datasources/'.Str::uuid().'/raw.json';
        $encoded = json_encode(array_values($result['records']));
        Storage::put($storagePath, $encoded);

        $dataSource->update([
            'raw_storage_path' => $storagePath,
            'file_size_bytes' => strlen($encoded),
            'is_partial' => $result['truncated'],
            'partial_reason' => $result['stopped_reason'],
        ]);
    }
}
