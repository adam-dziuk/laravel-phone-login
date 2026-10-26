<?php

namespace AdamDziuk\PhoneLogin\Concerns;

use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores the phone column encrypted, next to phone_hash, the HMAC the login
 * finds the user by. It is filled on every save, not only in the login, so a
 * user an admin adds by hand is found too instead of signing up again.
 *
 * @phpstan-require-extends Model
 */
trait EncryptsPhone
{
    public static function bootEncryptsPhone(): void
    {
        static::saving(function (Model $model): void {
            $phone = $model->getAttribute('phone');

            $model->setAttribute('phone_hash', is_string($phone) ? PhoneLogin::phoneHash($phone) : null);
        });
    }

    public function initializeEncryptsPhone(): void
    {
        $this->mergeCasts(['phone' => 'encrypted']);
    }
}
