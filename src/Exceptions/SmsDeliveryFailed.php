<?php

namespace AdamDziuk\PhoneLogin\Exceptions;

use RuntimeException;
use Throwable;

class SmsDeliveryFailed extends RuntimeException
{
    public static function to(string $phone, Throwable $previous): self
    {
        return new self("Could not send an SMS to {$phone}.", previous: $previous);
    }
}
