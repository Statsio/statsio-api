<?php

namespace App\Domain\Auth\Exceptions;

use Exception;

class InvalidRefreshTokenException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?: __('auth.unauthenticated'));
    }
}
