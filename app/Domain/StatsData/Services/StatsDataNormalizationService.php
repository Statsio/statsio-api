<?php

namespace App\Domain\StatsData\Services;

use InvalidArgumentException;

class StatsDataNormalizationService
{
    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<string, mixed>  $parsedRoot
     * @return list<array{keys: array<string, mixed>, values: array<string, mixed>}>
     */
    public function normalize(array $mapping, array $parsedRoot, int $maxRows): array
    {
        $rows = $this->extractRowList($parsedRoot, $mapping['rowPath'] ?? null);

        $keyFields = $mapping['keyFields'] ?? [];
        $valueFields = $mapping['valueFields'] ?? [];
        if (! is_array($keyFields) || ! is_array($valueFields)) {
            throw new InvalidArgumentException('normalization_mapping.keyFields and valueFields must be arrays.');
        }

        $staticKeys = $mapping['staticKeys'] ?? [];
        if ($staticKeys !== [] && ! is_array($staticKeys)) {
            throw new InvalidArgumentException('normalization_mapping.staticKeys must be an object/array.');
        }

        $out = [];
        $n = 0;
        foreach ($rows as $rawRow) {
            if ($n >= $maxRows) {
                break;
            }
            if (! is_array($rawRow)) {
                continue;
            }

            $keys = $this->projectFields($rawRow, $keyFields);
            $values = $this->projectFields($rawRow, $valueFields);
            foreach ($staticKeys as $k => $v) {
                $keys[(string) $k] = $v;
            }

            $out[] = ['keys' => $keys, 'values' => $values];
            $n++;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return list<array<string, mixed>>
     */
    private function extractRowList(array $parsedRoot, mixed $rowPath): array
    {
        if ($rowPath === null || $rowPath === '') {
            if (array_is_list($parsedRoot)) {
                return $parsedRoot;
            }

            return [$parsedRoot];
        }

        if (! is_string($rowPath)) {
            throw new InvalidArgumentException('normalization_mapping.rowPath must be a string or null.');
        }

        $at = data_get($parsedRoot, $rowPath);
        if ($at === null) {
            return [];
        }
        if (is_array($at) && array_is_list($at)) {
            return $at;
        }
        if (is_array($at)) {
            return [$at];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $rawRow
     * @param  list<array{name?: string, from?: string}>|array<int, mixed>  $fields
     * @return array<string, mixed>
     */
    private function projectFields(array $rawRow, array $fields): array
    {
        $out = [];
        foreach ($fields as $def) {
            if (! is_array($def)) {
                continue;
            }
            $name = $def['name'] ?? null;
            $from = $def['from'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            $path = is_string($from) && $from !== '' ? $from : $name;
            $out[$name] = data_get($rawRow, $path);
        }

        return $out;
    }
}
