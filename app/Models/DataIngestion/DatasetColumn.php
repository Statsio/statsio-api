<?php

namespace App\Models\DataIngestion;

use App\Domain\DataIngestion\Enums\ColumnTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetColumn extends Model
{
    protected $table = 'dataset_columns';

    protected $fillable = [
        'dataset_id',
        'name',
        'type',
        'nullable',
        'sample_values',
        'column_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ColumnTypeEnum::class,
            'nullable' => 'boolean',
            'sample_values' => 'array',
            'column_order' => 'integer',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
