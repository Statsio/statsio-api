<?php

namespace App\Domain\Ai\StudioTools\Concerns;

trait DecodesJsonArg
{
    /**
     * Décode un argument passé en chaîne JSON (les objets libres passent mal en
     * function-calling selon les fournisseurs — on les transporte en string).
     *
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    protected function jsonArg(array $input, string $key): array
    {
        $raw = $input[$key] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
