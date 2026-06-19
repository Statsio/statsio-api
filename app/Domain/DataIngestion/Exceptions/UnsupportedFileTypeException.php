<?php

namespace App\Domain\DataIngestion\Exceptions;

class UnsupportedFileTypeException extends \RuntimeException
{
    public function __construct(string $extension)
    {
        parent::__construct("Le format de fichier '{$extension}' n'est pas supporté. Formats acceptés : CSV, XLSX, JSON.");
    }
}
