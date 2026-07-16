<?php

namespace App\Services\DataIngestion;

class NumericValueParser
{
    /**
     * Normalise des valeurs comme "10,000+", "1,000,000+" ou "1.5M" (courantes dans
     * les jeux de données scrapés, ex. compteurs d'installations Google Play) en
     * float. Retourne null si la valeur n'est pas interprétable comme un nombre.
     */
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (is_numeric($trimmed)) {
            return (float) $trimmed;
        }

        if (! preg_match('/^([+-]?[\d,.\s]+)\s*([kKmMbB])?\+?$/', $trimmed, $matches)) {
            return null;
        }

        $numberPart = preg_replace('/[,\s]/', '', $matches[1]);
        if (substr_count($numberPart, '.') > 1 || ! is_numeric($numberPart)) {
            return null;
        }

        $base = (float) $numberPart;
        $suffix = strtolower($matches[2] ?? '');
        $multipliers = ['k' => 1e3, 'm' => 1e6, 'b' => 1e9];

        return $suffix !== '' ? $base * ($multipliers[$suffix] ?? 1) : $base;
    }
}
