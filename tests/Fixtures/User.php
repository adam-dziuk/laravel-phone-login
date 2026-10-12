<?php

namespace AdamDziuk\PhoneLogin\Tests\Fixtures;

use AdamDziuk\PhoneLogin\Concerns\ReceivesSms;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string|null $phone
 * @property string|null $name
 * @property CarbonInterface|null $phone_verified_at
 */
class User extends Authenticatable
{
    use ReceivesSms;

    protected $table = 'users';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
        ];
    }
}
