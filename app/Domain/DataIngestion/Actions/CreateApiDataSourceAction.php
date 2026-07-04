<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Jobs\ProcessDataSourceJob;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use App\Services\DataIngestion\HttpProbeService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateApiDataSourceAction
{
    public function __construct(
        private readonly HttpProbeService $httpProbe,
    ) {}

    /**
     * Appelle l'API externe, extrait les enregistrements et les fait passer par
     * le pipeline d'ingestion JSON existant (aucune pipeline dédiée aux sources API).
     *
     * @param  array<string, string>  $headers
     *
     * @throws ApiSourceFetchException
     */
    public function execute(
        User $user,
        string $name,
        string $url,
        string $method,
        array $headers,
        ?string $dataPath,
        string $authType = 'none',
        string $visibility = 'private',
        array $categories = [],
        ?int $provenanceId = null,
        ?string $provenanceOtherLabel = null,
    ): DataSource {
        $body = $this->httpProbe->fetch($url, $method, $headers);

        $records = $dataPath ? Arr::get($body, $dataPath) : $body;

        if (! is_array($records) || ! array_is_list($records)) {
            throw new ApiSourceFetchException(
                "La réponse de l'API ne contient pas de tableau d'enregistrements".($dataPath ? " au chemin '{$dataPath}'." : '.')
            );
        }

        $uuid = Str::uuid();
        $storagePath = "private/datasources/{$uuid}/raw.json";
        Storage::put($storagePath, json_encode(array_values($records)));

        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => $name,
            'type' => DataSourceTypeEnum::JSON,
            'source_kind' => 'api',
            'api_config' => [
                'url' => $url,
                'method' => strtoupper($method),
                'auth_type' => $authType,
                'headers' => $headers,
                'data_path' => $dataPath,
            ],
            'original_filename' => "{$name}.json",
            'raw_storage_path' => $storagePath,
            'file_size_bytes' => strlen(json_encode($records)),
            'status' => 'pending',
            'visibility' => $visibility,
            'categories' => $categories,
            'provenance_id' => $provenanceId,
            'provenance_other_label' => $provenanceOtherLabel,
        ]);

        Dataset::create([
            'data_source_id' => $dataSource->id,
            'user_id' => $dataSource->user_id,
            'name' => $dataSource->name,
            'status' => 'pending',
            'row_count' => 0,
        ]);

        ProcessDataSourceJob::dispatch($dataSource);

        return $dataSource;
    }
}
