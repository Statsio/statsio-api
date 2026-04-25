<?php

namespace App\Domain\StatsData\Services;

use App\Domain\StatsData\Support\FieldRef;
use InvalidArgumentException;

class StatsDataFormulaEngine
{
    private function invalidFormula(string $suffix = ''): InvalidArgumentException
    {
        $suffix = trim($suffix);
        $suffix = $suffix !== '' ? ' '.$suffix : '';
        try {
            return new InvalidArgumentException(__('stats_data.query_invalid_formula').$suffix);
        } catch (\Throwable) {
            return new InvalidArgumentException('Expression de formule invalide.'.$suffix);
        }
    }

    private function assertNumericForMath(mixed $raw): void
    {
        if ($raw === null) {
            return;
        }
        if (is_int($raw) || is_float($raw)) {
            return;
        }
        if (is_bool($raw)) {
            return;
        }
        if (is_string($raw)) {
            $s = trim(str_replace([' ', "\u{00A0}"], '', $raw));
            $s = str_replace(',', '.', $s);
            if ($s === '' || ! is_numeric($s)) {
                throw $this->invalidFormula('(opérande non numérique)');
            }
            return;
        }
        throw $this->invalidFormula('(opérande non numérique)');
    }
    /**
     * @param  array<string, mixed>  $expr
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    public function eval(array $expr, array $row, array $tuple): mixed
    {
        $kind = $expr['kind'] ?? null;
        if (! is_string($kind) || $kind === '') {
            throw $this->invalidFormula('(kind manquant)');
        }

        return match ($kind) {
            'number' => $this->asNumber($expr['value'] ?? null),
            'string' => is_string($expr['value'] ?? null) ? $expr['value'] : (string) ($expr['value'] ?? ''),
            'boolean' => (bool) ($expr['value'] ?? false),
            'null' => null,
            'ref' => $this->resolveRef((string) ($expr['ref'] ?? ''), $row, $tuple),
            'not' => ! $this->truthy($this->eval($this->requireExpr($expr, 'arg'), $row, $tuple)),
            'if' => $this->truthy($this->eval($this->requireExpr($expr, 'cond'), $row, $tuple))
                ? $this->eval($this->requireExpr($expr, 'then'), $row, $tuple)
                : $this->eval($this->requireExpr($expr, 'else'), $row, $tuple),
            'logic' => $this->evalLogic($expr, $row, $tuple),
            'cmp' => $this->evalCmp($expr, $row, $tuple),
            'op' => $this->evalOp($expr, $row, $tuple),
            'fn' => $this->evalFn($expr, $row, $tuple),
            default => throw $this->invalidFormula("(kind: {$kind})"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function requireExpr(array $expr, string $key): array
    {
        $v = $expr[$key] ?? null;
        if (! is_array($v)) {
            throw new InvalidArgumentException(__('stats_data.query_invalid_formula'));
        }

        /** @var array<string, mixed> $v */
        return $v;
    }

    private function truthy(mixed $v): bool
    {
        return (bool) $v;
    }

    private function asNumber(mixed $v): float|int|null
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if (is_string($v)) {
            $s = trim(str_replace([' ', "\u{00A0}"], '', $v));
            $s = str_replace(',', '.', $s);
            if ($s === '') {
                return null;
            }
            $n = is_numeric($s) ? (float) $s : null;
            return $n;
        }
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function resolveRef(string $ref, array $row, array $tuple): mixed
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        if (str_contains($ref, '.')) {
            $fr = FieldRef::parse($ref);
            if ($fr === null || ! isset($tuple[$fr->alias])) {
                return null;
            }

            return $tuple[$fr->alias][$fr->field] ?? null;
        }

        return $row[$ref] ?? null;
    }

    /**
     * @param  array<string, mixed>  $expr
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function evalLogic(array $expr, array $row, array $tuple): bool
    {
        $op = $expr['op'] ?? null;
        $args = $expr['args'] ?? null;
        if (! is_string($op) || ! is_array($args)) {
            throw new InvalidArgumentException(__('stats_data.query_invalid_formula').' (logic.op/args invalides)');
        }
        $args = array_values(array_filter($args, fn ($x) => is_array($x)));

        if ($op === 'and') {
            foreach ($args as $a) {
                if (! $this->truthy($this->eval($a, $row, $tuple))) {
                    return false;
                }
            }

            return true;
        }

        if ($op === 'or') {
            foreach ($args as $a) {
                if ($this->truthy($this->eval($a, $row, $tuple))) {
                    return true;
                }
            }

            return false;
        }

        throw new InvalidArgumentException(__('stats_data.query_invalid_formula'));
    }

    /**
     * @param  array<string, mixed>  $expr
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function evalCmp(array $expr, array $row, array $tuple): bool
    {
        $op = $expr['op'] ?? null;
        $args = $expr['args'] ?? null;
        if (! is_string($op) || ! is_array($args) || count($args) !== 2 || ! is_array($args[0]) || ! is_array($args[1])) {
            throw new InvalidArgumentException(__('stats_data.query_invalid_formula').' (cmp.op/args invalides)');
        }
        $a = $this->eval($args[0], $row, $tuple);
        $b = $this->eval($args[1], $row, $tuple);

        return match ($op) {
            'eq' => $a == $b,
            'ne' => $a != $b,
            'lt' => $a < $b,
            'lte' => $a <= $b,
            'gt' => $a > $b,
            'gte' => $a >= $b,
            default => throw new InvalidArgumentException(__('stats_data.query_invalid_formula')),
        };
    }

    /**
     * @param  array<string, mixed>  $expr
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function evalOp(array $expr, array $row, array $tuple): mixed
    {
        $op = $expr['op'] ?? null;
        $args = $expr['args'] ?? null;
        if (! is_string($op) || ! is_array($args)) {
            $opType = gettype($op);
            $argsType = gettype($args);
            throw $this->invalidFormula("(op/args invalides: op={$opType}, args={$argsType})");
        }
        $args = array_values(array_filter($args, fn ($x) => is_array($x)));

        if ($op === 'neg') {
            $raw = $this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple);
            $this->assertNumericForMath($raw);
            $x = $this->asNumber($raw);
            return $x === null ? null : -$x;
        }
        if ($op === 'abs') {
            $raw = $this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple);
            $this->assertNumericForMath($raw);
            $x = $this->asNumber($raw);
            return $x === null ? null : abs((float) $x);
        }

        // For arithmetic ops, reject non-numeric operands early (e.g. string labels).
        $raws = array_map(fn ($a) => $this->eval($a, $row, $tuple), $args);
        if (in_array($op, ['add', 'sub', 'mul', 'div', 'mod', 'pow'], true)) {
            foreach ($raws as $raw) {
                $this->assertNumericForMath($raw);
            }
        }
        $nums = array_map(fn ($v) => $this->asNumber($v), $raws);
        if ($nums === []) {
            return null;
        }

        return match ($op) {
            'add' => array_reduce($nums, fn ($acc, $n) => ($acc ?? 0) + ((float) ($n ?? 0)), 0.0),
            'sub' => $this->reduceBinary($nums, fn ($a, $b) => $a - $b),
            'mul' => array_reduce($nums, fn ($acc, $n) => ($acc ?? 1) * ((float) ($n ?? 1)), 1.0),
            'div' => $this->reduceBinary($nums, fn ($a, $b) => $b == 0 ? null : $a / $b),
            'mod' => $this->reduceBinary($nums, fn ($a, $b) => $b == 0 ? null : fmod($a, $b)),
            'pow' => $this->reduceBinary($nums, fn ($a, $b) => pow($a, $b)),
            default => throw $this->invalidFormula(),
        };
    }

    /**
     * @param  list<float|int|null>  $nums
     */
    private function reduceBinary(array $nums, callable $fn): float|int|null
    {
        $acc = $nums[0] ?? null;
        if ($acc === null) {
            $acc = 0.0;
        }
        for ($i = 1; $i < count($nums); $i++) {
            $b = $nums[$i] ?? null;
            $b = $b === null ? 0.0 : (float) $b;
            $next = $fn((float) $acc, $b);
            if ($next === null) {
                return null;
            }
            $acc = $next;
        }

        return $acc;
    }

    /**
     * @param  array<string, mixed>  $expr
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function evalFn(array $expr, array $row, array $tuple): mixed
    {
        $fn = $expr['fn'] ?? null;
        $args = $expr['args'] ?? null;
        if (! is_string($fn) || ! is_array($args)) {
            throw $this->invalidFormula('(fn/args invalides)');
        }
        $args = array_values(array_filter($args, fn ($x) => is_array($x)));

        return match ($fn) {
            'coalesce' => $this->fnCoalesce($args, $row, $tuple),
            'round' => $this->fnRound($args, $row, $tuple),
            'floor' => $this->fnFloor($args, $row, $tuple),
            'ceil' => $this->fnCeil($args, $row, $tuple),
            'min' => $this->fnMinMax($args, $row, $tuple, true),
            'max' => $this->fnMinMax($args, $row, $tuple, false),
            // text
            'concat' => $this->fnConcat($args, $row, $tuple),
            'upper' => $this->fnUpperLower($args, $row, $tuple, true),
            'lower' => $this->fnUpperLower($args, $row, $tuple, false),
            'upperFirst' => $this->fnUpperFirstLast($args, $row, $tuple, true),
            'upperLast' => $this->fnUpperFirstLast($args, $row, $tuple, false),
            'first' => $this->fnFirstLast($args, $row, $tuple, true),
            'last' => $this->fnFirstLast($args, $row, $tuple, false),
            default => throw $this->invalidFormula("(fn: {$fn})"),
        };
    }
 
    /**
     * @param  list<array<string, mixed>>  $args
     */
    private function fnConcat(array $args, array $row, array $tuple): string
    {
        $parts = [];
        foreach ($args as $a) {
            $v = $this->eval($a, $row, $tuple);
            if ($v === null) {
                $parts[] = '';
            } elseif (is_string($v)) {
                $parts[] = $v;
            } elseif (is_scalar($v)) {
                $parts[] = (string) $v;
            } else {
                $parts[] = '';
            }
        }
        return implode('', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $args
     */
    private function fnUpperLower(array $args, array $row, array $tuple, bool $upper): ?string
    {
        $v = $this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple);
        if ($v === null) return null;
        $s = is_string($v) ? $v : (is_scalar($v) ? (string) $v : null);
        if ($s === null) return null;
        return $upper ? mb_strtoupper($s) : mb_strtolower($s);
    }

    /**
     * @param  list<array<string, mixed>>  $args
     */
    private function fnUpperFirstLast(array $args, array $row, array $tuple, bool $first): ?string
    {
        $v = $this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple);
        if ($v === null) return null;
        $s = is_string($v) ? $v : (is_scalar($v) ? (string) $v : null);
        if ($s === null || $s === '') return $s;
        $len = mb_strlen($s);
        if ($len <= 1) return mb_strtoupper($s);
        if ($first) {
            return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
        }
        return mb_substr($s, 0, $len - 1).mb_strtoupper(mb_substr($s, $len - 1, 1));
    }

    /**
     * @param  list<array<string, mixed>>  $args
     */
    private function fnFirstLast(array $args, array $row, array $tuple, bool $first): ?string
    {
        $v = $this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple);
        if ($v === null) return null;
        $s = is_string($v) ? $v : (is_scalar($v) ? (string) $v : null);
        if ($s === null || $s === '') return $s;
        $len = mb_strlen($s);
        return $first ? mb_substr($s, 0, 1) : mb_substr($s, $len - 1, 1);
    }

    /**
     * @param  list<array<string, mixed>>  $args
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function fnCoalesce(array $args, array $row, array $tuple): mixed
    {
        foreach ($args as $a) {
            $v = $this->eval($a, $row, $tuple);
            if ($v !== null) {
                return $v;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $args
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function fnRound(array $args, array $row, array $tuple): float|int|null
    {
        $x = $this->asNumber($this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple));
        if ($x === null) {
            return null;
        }
        $p = $this->asNumber($this->eval($args[1] ?? ['kind' => 'number', 'value' => 0], $row, $tuple)) ?? 0;

        return round((float) $x, (int) $p);
    }

    /**
     * @param  list<array<string, mixed>>  $args
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function fnFloor(array $args, array $row, array $tuple): float|int|null
    {
        $x = $this->asNumber($this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple));
        return $x === null ? null : floor((float) $x);
    }

    /**
     * @param  list<array<string, mixed>>  $args
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function fnCeil(array $args, array $row, array $tuple): float|int|null
    {
        $x = $this->asNumber($this->eval($args[0] ?? ['kind' => 'null'], $row, $tuple));
        return $x === null ? null : ceil((float) $x);
    }

    /**
     * @param  list<array<string, mixed>>  $args
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>>  $tuple
     */
    private function fnMinMax(array $args, array $row, array $tuple, bool $min): float|int|null
    {
        $vals = [];
        foreach ($args as $a) {
            $n = $this->asNumber($this->eval($a, $row, $tuple));
            if ($n !== null) {
                $vals[] = (float) $n;
            }
        }
        if ($vals === []) {
            return null;
        }

        return $min ? min($vals) : max($vals);
    }
}

