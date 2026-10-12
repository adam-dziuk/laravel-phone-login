<?php

namespace AdamDziuk\PhoneLogin\Sms;

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Local development: the code lands in the log and nothing leaves the machine.
 */
class LogSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS → {$phone}: {$message}");
    }
}
