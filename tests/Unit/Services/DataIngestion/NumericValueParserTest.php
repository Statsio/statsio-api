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
        $this->assertNull(NumericValueParser::parse('N/A'));
        $this->assertNull(NumericValueParser::parse('2020-01-01'));
    }

    public function test_returns_null_when_multiple_decimal_points(): void
    {
        $this->assertNull(NumericValueParser::parse('1.2.3'));
    }

    public function test_strips_units_and_symbols(): void
    {
        $this->assertSame(90.0, NumericValueParser::parse('90%'));
        $this->assertSame(47.8, NumericValueParser::parse('  47,8 % '));
        $this->assertSame(1234.0, NumericValueParser::parse('1 234'));
        $this->assertSame(1234.56, NumericValueParser::parse('1 234,56 €'));
        $this->assertSame(-3.5, NumericValueParser::parse('-3,5'));
    }

    public function test_disambiguates_decimal_comma_from_thousands(): void
    {
        $this->assertSame(12.5, NumericValueParser::parse('12,5'));
        $this->assertSame(1234.0, NumericValueParser::parse('1,234'));
        $this->assertSame(1234.56, NumericValueParser::parse('1,234.56'));
    }
}
