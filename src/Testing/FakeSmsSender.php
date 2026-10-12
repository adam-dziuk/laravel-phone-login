<?php

namespace AdamDziuk\PhoneLogin\Testing;

use AdamDziuk\PhoneLogin\Contracts\SmsSender;

/**
 * Bind in tests to capture codes instead of sending them:
 *
 *     $sms = FakeSmsSender::bind();
 *     // ... post to the login form ...
 *     $code = $sms->lastCode();
 */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{phone: string, message: string}> */
    public array $sent = [];

    public static function bind(): self
    {
        $fake = new self;

        app()->instance(SmsSender::class, $fake);

        return $fake;
    }

    public function send(string $phone, string $message): void
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);

        if ($last === false) {
            return null;
        }

        preg_match('/\d{6}/', $last['message'], $matches);

        return $matches[0] ?? null;
    }
}
