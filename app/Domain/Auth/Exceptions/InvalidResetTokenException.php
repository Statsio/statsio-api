<?php

namespace App\Domain\Auth\Exceptions;

use Exception;

class InvalidResetTokenException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('auth.reset_token_invalid'));
    }
}
