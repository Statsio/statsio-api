<?php

namespace Tests\Unit;

use App\Domain\StatsData\Services\StatsDataFormulaEngine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StatsDataFormulaEngineTest extends TestCase
{
    public function test_ref_alias_field_and_add(): void
    {
        $eng = new StatsDataFormulaEngine();
        $tuple = ['s' => ['a' => 2, 'b' => 3]];
        $row = [];

        $expr = [
            'kind' => 'op',
            'op' => 'add',
            'args' => [
                ['kind' => 'ref', 'ref' => 's.a'],
                ['kind' => 'ref', 'ref' => 's.b'],
            ],
        ];

        $this->assertSame(5.0, $eng->eval($expr, $row, $tuple));
    }

    public function test_if_then_else(): void
    {
        $eng = new StatsDataFormulaEngine();
        $tuple = ['s' => ['x' => 10]];
        $row = [];

        $expr = [
            'kind' => 'if',
            'cond' => ['kind' => 'cmp', 'op' => 'gt', 'args' => [['kind' => 'ref', 'ref' => 's.x'], ['kind' => 'number', 'value' => 5]]],
            'then' => ['kind' => 'string', 'value' => 'ok'],
            'else' => ['kind' => 'string', 'value' => 'no'],
        ];

        $this->assertSame('ok', $eng->eval($expr, $row, $tuple));
    }

    public function test_math_ops_reject_non_numeric_strings(): void
    {
        $eng = new StatsDataFormulaEngine();
        $tuple = ['s' => ['codeDepartement' => '75', 'nom' => 'Paris']];
        $row = [];

        $expr = [
            'kind' => 'op',
            'op' => 'add',
            'args' => [
                ['kind' => 'ref', 'ref' => 's.codeDepartement'],
                ['kind' => 'ref', 'ref' => 's.nom'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $eng->eval($expr, $row, $tuple);
    }

    public function test_text_concat_and_upper(): void
    {
        $eng = new StatsDataFormulaEngine();
        $tuple = ['s' => ['a' => 'par', 'b' => 'is']];
        $row = [];

        $expr = [
            'kind' => 'fn',
            'fn' => 'upper',
            'args' => [
                [
                    'kind' => 'fn',
                    'fn' => 'concat',
                    'args' => [
                        ['kind' => 'ref', 'ref' => 's.a'],
                        ['kind' => 'ref', 'ref' => 's.b'],
                    ],
                ],
            ],
        ];

        $this->assertSame('PARIS', $eng->eval($expr, $row, $tuple));
    }
}

