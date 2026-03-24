<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

class InvalidGoogleTokenException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?: __('auth.google_token_invalid'));
    }
}
