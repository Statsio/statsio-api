<?php

namespace App\Domain\StatsData\Support;

final class StableKey
{
    public static function part(mixed $v): string
    {
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v) || is_float($v)) {
            return 'n:'.(string) $v;
        }
        if (is_string($v)) {
            return 's:'.$v;
        }

        return 'j:'.json_encode($v);
    }
}

