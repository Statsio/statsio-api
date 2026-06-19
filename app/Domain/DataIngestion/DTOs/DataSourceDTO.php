<?php

namespace App\Domain\DataIngestion\DTOs;

use App\Domain\DataIngestion\Enums\DataSourceStatusEnum;
use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;

final class DataSourceDTO
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $name,
        public readonly DataSourceTypeEnum $type,
        public readonly string $originalFilename,
        public readonly DataSourceStatusEnum $status,
        public readonly int $fileSizeBytes,
        public readonly ?string $errorMessage,
        public readonly ?\DateTimeImmutable $processedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'original_filename' => $this->originalFilename,
            'status' => $this->status->value,
            'file_size_bytes' => $this->fileSizeBytes,
            'error_message' => $this->errorMessage,
            'processed_at' => $this->processedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
