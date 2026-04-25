<?php

namespace Tests\Unit;

use App\Domain\StatsData\Services\StatsDataNormalizationService;
use PHPUnit\Framework\TestCase;

class StatsDataNormalizationServiceTest extends TestCase
{
    public function test_normalizes_rows_with_row_path_and_fields(): void
    {
        $svc = new StatsDataNormalizationService;
        $mapping = [
            'rowPath' => 'items',
            'keyFields' => [
                ['name' => 'code_insee', 'from' => 'geo.code'],
            ],
            'valueFields' => [
                ['name' => 'population', 'from' => 'pop'],
            ],
            'staticKeys' => ['source' => 'test'],
        ];
        $root = [
            'items' => [
                ['geo' => ['code' => '69091'], 'pop' => 50000],
                ['geo' => ['code' => '75056'], 'pop' => 2_000_000],
            ],
        ];

        $rows = $svc->normalize($mapping, $root, 100);

        $this->assertCount(2, $rows);
        $this->assertSame('69091', $rows[0]['keys']['code_insee']);
        $this->assertSame(50000, $rows[0]['values']['population']);
        $this->assertSame('test', $rows[0]['keys']['source']);
    }

    public function test_root_list_without_row_path(): void
    {
        $svc = new StatsDataNormalizationService;
        $mapping = [
            'valueFields' => [
                ['name' => 'x', 'from' => 'a'],
            ],
        ];
        $root = [
            ['a' => 1],
            ['a' => 2],
        ];

        $rows = $svc->normalize($mapping, $root, 10);

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0]['values']['x']);
    }

    public function test_nested_api_shape_with_explicit_row_path_and_from(): void
    {
        $svc = new StatsDataNormalizationService;
        $mapping = [
            'rowPath' => 'observations',
            'keyFields' => [
                ['name' => 'TIME_PERIOD', 'from' => 'dimensions.TIME_PERIOD'],
            ],
            'valueFields' => [
                ['name' => 'OBS_VALUE_NIVEAU', 'from' => 'measures.OBS_VALUE_NIVEAU.value'],
            ],
        ];
        $root = [
            'observations' => [
                [
                    'dimensions' => ['TIME_PERIOD' => '2004-03', 'GEO' => 'FR'],
                    'measures' => ['OBS_VALUE_NIVEAU' => ['value' => 7596.0]],
                ],
                [
                    'dimensions' => ['TIME_PERIOD' => '2004-04'],
                    'measures' => ['OBS_VALUE_NIVEAU' => ['value' => 7656.0]],
                ],
            ],
        ];

        $rows = $svc->normalize($mapping, $root, 100);

        $this->assertCount(2, $rows);
        $this->assertSame('2004-03', $rows[0]['keys']['TIME_PERIOD']);
        $this->assertSame(7596.0, $rows[0]['values']['OBS_VALUE_NIVEAU']);
    }
}
