<?php

namespace AdamDziuk\PhoneLogin\Concerns;

use AdamDziuk\PhoneLogin\PhoneLogin;
use AdamDziuk\PhoneLogin\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores the phone column encrypted, next to phone_hash, the HMAC the login
 * finds the user by. It is filled on every save, not only in the login, so a
 * user an admin adds by hand is found too instead of signing up again. The
 * number is saved as the login normalises it, "600 123 456" as
 * "+48600123456"; one that cannot be normalised is kept as typed.
 *
 * @phpstan-require-extends Model
 */
trait EncryptsPhone
{
    public static function bootEncryptsPhone(): void
    {
        static::saving(function (Model $model): void {
            $phone = $model->getAttribute('phone');

            if (is_string($phone)) {
                $phone = app(PhoneNumber::class)->normalise($phone) ?? $phone;
                $model->setAttribute('phone', $phone);
            }

            $model->setAttribute('phone_hash', is_string($phone) ? PhoneLogin::phoneHash($phone) : null);
        });
    }

    public function initializeEncryptsPhone(): void
    {
        $this->mergeCasts(['phone' => 'encrypted']);
    }
}
