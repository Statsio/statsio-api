<?php

namespace Tests\Unit\Services\DataIngestion;

use App\Services\DataIngestion\NumericValueParser;
use Tests\TestCase;

class NumericValueParserTest extends TestCase
{
    public function test_parses_int_and_float_as_is(): void
    {
        $this->assertSame(42.0, NumericValueParser::parse(42));
        $this->assertSame(4.2, NumericValueParser::parse(4.2));
    }

    public function test_parses_plain_numeric_strings(): void
    {
        $this->assertSame(1234.0, NumericValueParser::parse('1234'));
        $this->assertSame(1.5, NumericValueParser::parse('1.5'));
    }

    public function test_returns_null_for_non_numeric_non_string(): void
    {
        $this->assertNull(NumericValueParser::parse(['not', 'numeric']));
        $this->assertNull(NumericValueParser::parse(null));
    }

    public function test_returns_null_for_empty_string(): void
    {
        $this->assertNull(NumericValueParser::parse('   '));
    }

    public function test_parses_comma_separated_thousands_with_trailing_plus(): void
    {
        $this->assertSame(10000.0, NumericValueParser::parse('10,000+'));
        $this->assertSame(1000000.0, NumericValueParser::parse('1,000,000+'));
    }

    public function test_parses_suffixed_shorthand_values(): void
    {
        $this->assertSame(1500000.0, NumericValueParser::parse('1.5M'));
        $this->assertSame(10000.0, NumericValueParser::parse('10k'));
        $this->assertSame(2000000000.0, NumericValueParser::parse('2B'));
    }

    public function test_returns_null_for_unparseable_string(): void
    {
        $this->assertNull(NumericValueParser::parse('not a number'));
    }

    public function test_returns_null_when_multiple_decimal_points(): void
    {
        $this->assertNull(NumericValueParser::parse('1.2.3'));
    }
}
