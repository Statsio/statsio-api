<?php

namespace App\Services\DataIngestion;

class NumericValueParser
{
    /**
     * Normalise une valeur de cellule en float, en ignorant toute décoration non
     * numérique : symboles (« 90 % », « 12 € »), séparateurs de milliers
     * (« 1 234 », « 10,000 »), « + » de troncature (« 10,000+ »), suffixes
     * d'échelle (« 1.5M », « 10k », « 2B »). Retourne null si aucune valeur
     * numérique n'est interprétable.
     *
     * Aligné sur `parseNumericValue()` côté front (statsio-front) pour que
     * graphiques, filtres (<, >), KPI et agrégats voient le même nombre.
     */
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_nan((float) $value) || is_infinite((float) $value) ? null : (float) $value;
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

        // Suffixe d'échelle collé au nombre (« 1.5M », « 10k »).
        $multiplier = 1.0;
        if (preg_match('/^([+-]?[\d,.\s]+)\s*([kKmMbB])\+?$/', $trimmed, $m)) {
            $trimmed = $m[1];
            $multiplier = ['k' => 1e3, 'm' => 1e6, 'b' => 1e9][strtolower($m[2])];
        }

        $s = preg_replace('/\s/', '', $trimmed) ?? '';
        $hasDot = str_contains($s, '.');
        $hasComma = str_contains($s, ',');
        if ($hasDot && $hasComma) {
            $s = str_replace(',', '', $s); // « 1,234.56 » → virgule = séparateur de milliers
        } elseif ($hasComma) {
            // « 12,5 » → décimale ; « 1,234 » / « 10,000 » → milliers.
            $digitsAndComma = preg_replace('/[^0-9,]/', '', $s) ?? '';
            $s = substr_count($s, ',') === 1 && preg_match('/,\d{1,2}$/', $digitsAndComma)
                ? str_replace(',', '.', $s)
                : str_replace(',', '', $s);
        }

        // Retire toute décoration restante (%, €, lettres, « + »…).
        $cleaned = preg_replace('/[^0-9.\-]/', '', $s) ?? '';

        if ($cleaned === '' || ! preg_match('/\d/', $cleaned) || ! is_numeric($cleaned)) {
            return null;
        }

        return ((float) $cleaned) * $multiplier;
    }
}
