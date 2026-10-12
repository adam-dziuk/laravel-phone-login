<?php

namespace AdamDziuk\PhoneLogin\Concerns;

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;
use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Texts the user through the same SMS driver as the login codes, at the
 * number in the phone column from phone-login.columns.
 *
 * @phpstan-require-extends Model
 */
trait ReceivesSms
{
    /**
     * Sends at once, without a queue. A model without a number throws rather
     * than skipping the message: whoever sent it would think it went out.
     *
     * @throws SmsDeliveryFailed
     */
    public function sendMessage(string $message): void
    {
        $column = (string) PhoneLogin::column('phone');
        $phone = $this->getAttribute($column);

        if (! is_string($phone) || $phone === '') {
            throw new LogicException('Cannot text ['.static::class.'] '.$this->getKey().": it has no phone number in its [{$column}] column.");
        }

        app(SmsSender::class)->send($phone, $message);
    }
}
