<?php

namespace App\Domain\StatsData\Parsers;

use Throwable;

class StatsDataXmlParser implements StatsDataFormatParser
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

        libxml_use_internal_errors(true);
        try {
            $sx = simplexml_load_string($trim, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } catch (Throwable) {
            libxml_clear_errors();

            return [];
        }
        if ($sx === false) {
            libxml_clear_errors();

            return [];
        }
        libxml_clear_errors();

        $json = json_encode($sx);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return [];
        }

        return $data;
    }
}
