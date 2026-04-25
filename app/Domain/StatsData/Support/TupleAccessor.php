<?php

namespace App\Domain\StatsData\Support;

final class TupleAccessor
{
    /**
     * @param  array<string, array<string, mixed>>  $tuple
     */
    public static function get(array $tuple, string $from): mixed
    {
        $ref = FieldRef::parse($from);
        if ($ref === null) {
            return null;
        }
        if (! isset($tuple[$ref->alias])) {
            return null;
        }

        return $tuple[$ref->alias][$ref->field] ?? null;
    }
}

