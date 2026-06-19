<?php

namespace App\Domain\DataIngestion\Enums;

enum DataSourceTypeEnum: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case JSON = 'json';

    public static function fromMimeType(string $mimeType): self
    {
        return match ($mimeType) {
            'text/csv', 'text/plain' => self::CSV,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel' => self::XLSX,
            'application/json' => self::JSON,
            default => throw new \ValueError("Unsupported MIME type: {$mimeType}"),
        };
    }

    public static function fromExtension(string $extension): self
    {
        return match (strtolower($extension)) {
            'csv' => self::CSV,
            'xlsx', 'xls' => self::XLSX,
            'json' => self::JSON,
            default => throw new \ValueError("Unsupported extension: {$extension}"),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CSV => 'Fichier CSV',
            self::XLSX => 'Fichier Excel',
            self::JSON => 'Fichier JSON',
        };
    }
}
