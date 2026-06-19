<?php

namespace App\Domain\DataIngestion\Exceptions;

class FileParsingException extends \RuntimeException
{
    public function __construct(string $filename, string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("Impossible de lire le fichier '{$filename}' : {$reason}", 0, $previous);
    }
}
