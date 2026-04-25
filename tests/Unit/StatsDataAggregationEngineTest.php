<?php

namespace Tests\Unit;

use App\Domain\StatsData\Services\StatsDataAggregationEngine;
use App\Domain\StatsData\Services\StatsDataFormulaEngine;
use PHPUnit\Framework\TestCase;

class StatsDataAggregationEngineTest extends TestCase
{
    public function test_group_and_sum(): void
    {
        $agg = new StatsDataAggregationEngine(new StatsDataFormulaEngine());

        $rows = [
            ['city' => 'A', 'value' => 2],
            ['city' => 'A', 'value' => 3],
            ['city' => 'B', 'value' => 10],
        ];

        $out = $agg->groupAndAggregate(
            $rows,
            ['city'],
            [
                ['label' => 'Total', 'fn' => 'sum', 'expr' => ['kind' => 'ref', 'ref' => 'value']],
                ['label' => 'Count', 'fn' => 'count'],
            ],
        );

        $byCity = [];
        foreach ($out as $r) {
            $byCity[$r['city']] = $r;
        }

        $this->assertSame(5.0, $byCity['A']['Total']);
        $this->assertSame(2, $byCity['A']['Count']);
        $this->assertSame(10.0, $byCity['B']['Total']);
        $this->assertSame(1, $byCity['B']['Count']);
    }
}

