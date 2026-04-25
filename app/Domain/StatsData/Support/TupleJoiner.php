<?php

namespace App\Domain\StatsData\Support;

final class TupleJoiner
{
    /**
     * Hash join tuples across aliases, using fields in $on.
     *
     * @param  array<string, list<array<string, mixed>>>  $rowsByAlias
     * @param  list<string>  $on
     * @return list<array<string, array<string, mixed>>>
     */
    public static function hashJoin(array $rowsByAlias, array $on, bool $left): array
    {
        $aliases = array_keys($rowsByAlias);
        $firstAlias = $aliases[0] ?? null;
        if ($firstAlias === null) {
            return [];
        }
        $rest = array_slice($aliases, 1);

        $tuples = [];
        foreach ($rowsByAlias[$firstAlias] as $row) {
            $tuples[] = [$firstAlias => $row];
        }

        foreach ($rest as $alias) {
            $index = self::buildRowIndex($rowsByAlias[$alias], $on);
            $newTuples = [];
            foreach ($tuples as $tuple) {
                $keyVals = self::extractJoinKeysFromTuple($tuple, $on);
                $k = self::joinKey($keyVals);
                $matches = $index[$k] ?? [];
                if ($matches !== []) {
                    foreach ($matches as $nr) {
                        $newTuples[] = array_merge($tuple, [$alias => $nr]);
                    }
                } elseif ($left) {
                    $newTuples[] = array_merge($tuple, [$alias => []]);
                }
            }
            $tuples = $newTuples;
            if (! $left && $tuples === []) {
                break;
            }
        }

        return $tuples;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $on
     * @return array<string, list<array<string, mixed>>>
     */
    private static function buildRowIndex(array $rows, array $on): array
    {
        $out = [];
        foreach ($rows as $r) {
            foreach ($on as $f) {
                if (! array_key_exists($f, $r)) {
                    continue 2;
                }
            }
            $vals = [];
            foreach ($on as $f) {
                $vals[$f] = $r[$f];
            }
            $k = self::joinKey($vals);
            $out[$k] ??= [];
            $out[$k][] = $r;
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $tuple
     * @param  list<string>  $on
     * @return array<string, mixed>
     */
    private static function extractJoinKeysFromTuple(array $tuple, array $on): array
    {
        $out = [];
        foreach ($on as $field) {
            $out[$field] = null;
            foreach ($tuple as $flat) {
                if (array_key_exists($field, $flat)) {
                    $out[$field] = $flat[$field];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $vals
     */
    private static function joinKey(array $vals): string
    {
        $parts = [];
        foreach ($vals as $v) {
            $parts[] = StableKey::part($v);
        }

        return implode('|', $parts);
    }
}

