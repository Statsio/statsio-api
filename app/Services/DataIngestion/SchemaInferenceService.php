<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\DTOs\ParsedFileDTO;
use App\Domain\DataIngestion\Enums\ColumnTypeEnum;

class SchemaInferenceService
{
    private const SAMPLE_SIZE = 200;
    private const SAMPLE_VALUES_COUNT = 5;

    private const DATE_PATTERNS = [
        '/^\d{4}-\d{2}-\d{2}$/',                      // 2024-01-15
        '/^\d{2}\/\d{2}\/\d{4}$/',                    // 15/01/2024
        '/^\d{2}-\d{2}-\d{4}$/',                      // 15-01-2024
    ];

    private const DATETIME_PATTERNS = [
        '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?/',  // ISO 8601
    ];

    private const BOOLEAN_VALUES = ['true', 'false', 'yes', 'no', 'oui', 'non', '1', '0'];

    /**
     * @return array<string, array{type: ColumnTypeEnum, nullable: bool, sample_values: array}>
     */
    public function infer(ParsedFileDTO $parsed): array
    {
        $sample = $parsed->sample(self::SAMPLE_SIZE);
        $schema = [];

        foreach ($parsed->headers as $column) {
            $values = array_column($sample, $column);
            $nonNullValues = array_filter($values, fn ($v) => $v !== null && $v !== '');
            $nullable = count($nonNullValues) < count($values);

            $schema[$column] = [
                'type' => $this->inferType(array_values($nonNullValues)),
                'nullable' => $nullable,
                'sample_values' => $this->collectSamples($nonNullValues),
            ];
        }

        return $schema;
    }

    private function inferType(array $values): ColumnTypeEnum
    {
        if (empty($values)) {
            return ColumnTypeEnum::STRING;
        }

        if ($this->allMatch($values, fn ($v) => $this->isDatetime($v))) {
            return ColumnTypeEnum::DATETIME;
        }

        if ($this->allMatch($values, fn ($v) => $this->isDate($v))) {
            return ColumnTypeEnum::DATE;
        }

        if ($this->allMatch($values, fn ($v) => $this->isBoolean($v))) {
            return ColumnTypeEnum::BOOLEAN;
        }

        if ($this->allMatch($values, fn ($v) => $this->isInteger($v))) {
            return ColumnTypeEnum::INTEGER;
        }

        if ($this->allMatch($values, fn ($v) => $this->isFloat($v))) {
            return ColumnTypeEnum::FLOAT;
        }

        return ColumnTypeEnum::STRING;
    }

    private function allMatch(array $values, callable $predicate): bool
    {
        foreach ($values as $value) {
            if (!$predicate($value)) {
                return false;
            }
        }
        return true;
    }

    private function isInteger(string $value): bool
    {
        return preg_match('/^-?\d+$/', trim($value)) === 1;
    }

    private function isFloat(string $value): bool
    {
        $normalized = str_replace(',', '.', trim($value));
        return is_numeric($normalized) && str_contains($normalized, '.');
    }

    private function isBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::BOOLEAN_VALUES, true);
    }

    private function isDate(string $value): bool
    {
        $value = trim($value);
        foreach (self::DATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    private function isDatetime(string $value): bool
    {
        $value = trim($value);
        foreach (self::DATETIME_PATTERNS as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    private function collectSamples(array $values): array
    {
        $unique = array_unique(array_values($values));
        return array_slice($unique, 0, self::SAMPLE_VALUES_COUNT);
    }
}
