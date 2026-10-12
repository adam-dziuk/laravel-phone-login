<?php

namespace AdamDziuk\PhoneLogin\Contracts;

use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;

interface SmsSender
{
    /**
     * Send synchronously: the form shows a friendly error when this throws,
     * which a queued notification could never do.
     *
     * @throws SmsDeliveryFailed
     */
    public function send(string $phone, string $message): void;
}
