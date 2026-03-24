<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

class GoogleAuthConfigurationException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?: __('auth.google_client_id_missing'));
    }
}
