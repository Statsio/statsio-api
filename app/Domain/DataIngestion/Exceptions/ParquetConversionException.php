<?php

namespace App\Domain\DataIngestion\Exceptions;

class ParquetConversionException extends \RuntimeException
{
    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("Échec de la conversion Parquet : {$reason}", 0, $previous);
    }
}
