<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Domain\DataIngestion\Exceptions\UnsupportedFileTypeException;
use App\Services\DataIngestion\Contracts\FileParserInterface;
use App\Services\DataIngestion\Parsers\CsvParser;
use App\Services\DataIngestion\Parsers\JsonParser;
use App\Services\DataIngestion\Parsers\XlsxParser;

class ParserFactory
{
    public function make(DataSourceTypeEnum $type): FileParserInterface
    {
        return match ($type) {
            DataSourceTypeEnum::CSV => new CsvParser(),
            DataSourceTypeEnum::XLSX => new XlsxParser(),
            DataSourceTypeEnum::JSON => new JsonParser(),
        };
    }

    public function makeFromExtension(string $extension): FileParserInterface
    {
        try {
            $type = DataSourceTypeEnum::fromExtension($extension);
        } catch (\ValueError) {
            throw new UnsupportedFileTypeException($extension);
        }

        return $this->make($type);
    }
}
