<?php

namespace App\Domain\Auth\Exceptions;

use Exception;

class InvalidVerificationCodeException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('auth.verification_code_invalid'));
    }
}
