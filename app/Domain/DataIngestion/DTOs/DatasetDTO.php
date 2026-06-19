<?php

namespace App\Domain\DataIngestion\DTOs;

use App\Domain\DataIngestion\Enums\DatasetStatusEnum;

final class DatasetDTO
{
    /**
     * @param array<int, array{name: string, type: string, nullable: bool}> $columns
     */
    public function __construct(
        public readonly int $id,
        public readonly int $dataSourceId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $parquetPath,
        public readonly int $rowCount,
        public readonly DatasetStatusEnum $status,
        public readonly array $columns,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'data_source_id' => $this->dataSourceId,
            'name' => $this->name,
            'description' => $this->description,
            'parquet_path' => $this->parquetPath,
            'row_count' => $this->rowCount,
            'status' => $this->status->value,
            'columns' => $this->columns,
        ];
    }
}
