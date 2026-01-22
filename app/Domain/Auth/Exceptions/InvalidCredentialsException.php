<?php
namespace App\Domain\Auth\Exceptions;

use Exception;

class InvalidCredentialsException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('errors.invalid_credentials'));
    }
}

?>
