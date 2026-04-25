<?php

namespace App\Domain\StatsData\Parsers;

use JsonException;

class StatsDataJsonParser implements StatsDataFormatParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $raw): array
    {
        $trim = trim($raw);
        if ($trim === '') {
            return [];
        }

        try {
            $data = json_decode($trim, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
