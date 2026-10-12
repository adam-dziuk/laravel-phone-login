<?php

namespace AdamDziuk\PhoneLogin\Models;

use AdamDziuk\PhoneLogin\Concerns\ReceivesSms;
use AdamDziuk\PhoneLogin\PhoneLogin;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The user the package logs in when the app has no guard of its own for
 * phone users. Its table is named in phone-login.users.table. Extend it to
 * add relations and point phone-login.users.model at the subclass.
 *
 * @property int $id
 * @property string $phone
 * @property CarbonInterface|null $phone_verified_at
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class PhoneUser extends Authenticatable
{
    use ReceivesSms;

    protected $hidden = ['remember_token'];

    /**
     * A $table set explicitly on a subclass wins over the config.
     */
    public function getTable(): string
    {
        return $this->table ?? PhoneLogin::usersTable();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $verifiedAt = PhoneLogin::column('verified_at');

        return $verifiedAt === null ? [] : [$verifiedAt => 'datetime'];
    }
}
