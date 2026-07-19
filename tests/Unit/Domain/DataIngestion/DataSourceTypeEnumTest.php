<?php

namespace Tests\Unit\Domain\DataIngestion;

use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use Tests\TestCase;

class DataSourceTypeEnumTest extends TestCase
{
    public function test_from_mime_type_maps_known_types(): void
    {
        $this->assertSame(DataSourceTypeEnum::CSV, DataSourceTypeEnum::fromMimeType('text/csv'));
        $this->assertSame(DataSourceTypeEnum::CSV, DataSourceTypeEnum::fromMimeType('text/plain'));
        $this->assertSame(DataSourceTypeEnum::XLSX, DataSourceTypeEnum::fromMimeType(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ));
        $this->assertSame(DataSourceTypeEnum::XLSX, DataSourceTypeEnum::fromMimeType('application/vnd.ms-excel'));
        $this->assertSame(DataSourceTypeEnum::JSON, DataSourceTypeEnum::fromMimeType('application/json'));
        $this->assertSame(DataSourceTypeEnum::PARQUET, DataSourceTypeEnum::fromMimeType('application/vnd.apache.parquet'));
        $this->assertSame(DataSourceTypeEnum::PARQUET, DataSourceTypeEnum::fromMimeType('application/octet-stream'));
    }

    public function test_from_mime_type_throws_for_unsupported_type(): void
    {
        $this->expectException(\ValueError::class);

        DataSourceTypeEnum::fromMimeType('application/x-unknown');
    }

    public function test_from_extension_maps_known_extensions_case_insensitively(): void
    {
        $this->assertSame(DataSourceTypeEnum::CSV, DataSourceTypeEnum::fromExtension('CSV'));
        $this->assertSame(DataSourceTypeEnum::XLSX, DataSourceTypeEnum::fromExtension('xlsx'));
        $this->assertSame(DataSourceTypeEnum::XLSX, DataSourceTypeEnum::fromExtension('XLS'));
        $this->assertSame(DataSourceTypeEnum::JSON, DataSourceTypeEnum::fromExtension('json'));
        $this->assertSame(DataSourceTypeEnum::PARQUET, DataSourceTypeEnum::fromExtension('parquet'));
    }

    public function test_from_extension_throws_for_unsupported_extension(): void
    {
        $this->expectException(\ValueError::class);

        DataSourceTypeEnum::fromExtension('exe');
    }

    public function test_label_returns_french_description_for_each_case(): void
    {
        $this->assertSame('Fichier CSV', DataSourceTypeEnum::CSV->label());
        $this->assertSame('Fichier Excel', DataSourceTypeEnum::XLSX->label());
        $this->assertSame('Fichier JSON', DataSourceTypeEnum::JSON->label());
        $this->assertSame('Fichier Parquet', DataSourceTypeEnum::PARQUET->label());
    }
}
