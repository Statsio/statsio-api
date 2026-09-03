<?php

namespace Tests\Unit\Domain\DataIngestion;

use App\Domain\DataIngestion\Enums\DataSourceRefreshFrequencyEnum as Freq;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DataSourceRefreshFrequencyEnumTest extends TestCase
{
    public function test_hourly_case_exists_and_is_labelled(): void
    {
        $this->assertSame(Freq::HOURLY, Freq::from('hourly'));
        $this->assertSame('Toutes les heures', Freq::HOURLY->label());
        $this->assertSame('Jamais', Freq::NONE->label());
    }

    public function test_next_occurrence_advances_by_the_right_interval(): void
    {
        $from = CarbonImmutable::parse('2026-09-03 10:00:00');

        $this->assertNull(Freq::NONE->nextOccurrenceFrom($from));
        $this->assertTrue($from->addHour()->equalTo(Freq::HOURLY->nextOccurrenceFrom($from)));
        $this->assertTrue($from->addDay()->equalTo(Freq::DAILY->nextOccurrenceFrom($from)));
        $this->assertTrue($from->addWeek()->equalTo(Freq::WEEKLY->nextOccurrenceFrom($from)));
        $this->assertTrue($from->addMonth()->equalTo(Freq::MONTHLY->nextOccurrenceFrom($from)));
        $this->assertTrue($from->addYear()->equalTo(Freq::YEARLY->nextOccurrenceFrom($from)));
    }
}
