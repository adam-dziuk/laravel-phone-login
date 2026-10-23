<?php

namespace AdamDziuk\PhoneLogin\Concerns;

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Texts the user through the same SMS driver as the login codes, at the
 * number in its phone column.
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
        $phone = $this->getAttribute('phone');

        if (! is_string($phone) || $phone === '') {
            throw new LogicException('Cannot text ['.static::class.'] '.$this->getKey().': it has no phone number.');
        }

        app(SmsSender::class)->send($phone, $message);
    }
}
