<?php

namespace App\Domain\DataIngestion\Actions;

use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Domain\DataIngestion\Exceptions\UnsupportedFileTypeException;
use App\Jobs\ProcessDataSourceJob;
use App\Jobs\ProcessParquetJob;
use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadDataSourceAction
{
    /**
     * Stocke le fichier brut, crée la DataSource et dispatch le job de traitement.
     *
     * @throws UnsupportedFileTypeException
     */
    public function execute(
        UploadedFile $file,
        User $user,
        ?string $name = null,
        string $visibility = 'private',
        array $categories = [],
        ?int $provenanceId = null,
        ?string $provenanceOtherLabel = null,
        ?string $sheetName = null,
        ?int $headerRow = null,
        ?array $excludedRows = null,
    ): DataSource {
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $type = DataSourceTypeEnum::fromExtension($extension);
        } catch (\ValueError) {
            throw new UnsupportedFileTypeException($extension);
        }

        $originalFilename = $file->getClientOriginalName();
        $storagePath = $this->storeRawFile($file);

        $dataSource = DataSource::create([
            'user_id' => $user->id,
            'name' => $name ?? pathinfo($originalFilename, PATHINFO_FILENAME),
            'type' => $type,
            'source_kind' => 'upload',
            'original_filename' => $originalFilename,
            'sheet_name' => $sheetName,
            'header_row' => $headerRow,
            'excluded_rows' => $excludedRows,
            'raw_storage_path' => $storagePath,
            'file_size_bytes' => $file->getSize(),
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

        if ($type === DataSourceTypeEnum::PARQUET) {
            ProcessParquetJob::dispatch($dataSource);
        } else {
            ProcessDataSourceJob::dispatch($dataSource);
        }

        return $dataSource;
    }

    private function storeRawFile(UploadedFile $file): string
    {
        $uuid = Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $path = "datasources/{$uuid}/raw.{$extension}";

        Storage::put($path, file_get_contents($file->getRealPath()));

        return $path;
    }
}
