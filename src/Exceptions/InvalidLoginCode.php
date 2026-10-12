<?php

namespace AdamDziuk\PhoneLogin\Exceptions;

use RuntimeException;

/**
 * One exception for a wrong, expired, used-up or replayed code — telling them
 * apart would only help someone guessing.
 */
class InvalidLoginCode extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The login code is invalid, has expired or has already been used.');
    }
}
