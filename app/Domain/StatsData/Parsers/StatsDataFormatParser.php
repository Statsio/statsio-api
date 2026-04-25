<?php

namespace App\Domain\StatsData\Parsers;

interface StatsDataFormatParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $raw): array;
}
