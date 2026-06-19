<?php

namespace App\Models\DataIngestion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetVersion extends Model
{
    protected $table = 'dataset_versions';

    protected $fillable = [
        'dataset_id',
        'version_number',
        'parquet_storage_path',
        'file_size_bytes',
        'row_count',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'row_count' => 'integer',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
