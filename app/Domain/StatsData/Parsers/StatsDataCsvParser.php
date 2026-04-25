<?php

namespace App\Domain\StatsData\Parsers;

class StatsDataCsvParser implements StatsDataFormatParser
{
    /**
     * First row = headers. Returns a list of associative rows.
     *
     * @return array<string, mixed>
     */
    public function parse(string $raw): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $normalized);
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) $l) !== ''));
        if ($lines === []) {
            return [];
        }

        $headerLine = array_shift($lines);
        $headers = str_getcsv((string) $headerLine);
        $headers = array_map(fn ($h) => trim((string) $h), $headers);
        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv((string) $line);
            if (count($cells) === 1 && trim((string) $cells[0]) === '') {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = $cells[$i] ?? null;
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
