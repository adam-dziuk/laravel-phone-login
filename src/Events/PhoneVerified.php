<?php

namespace AdamDziuk\PhoneLogin\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched inside the login transaction, right after the code is consumed
 * and the user saved, before the session login. A synchronous listener
 * commits or rolls back together with the login: if it throws, the code stays
 * unused and a new user is never created.
 */
final class PhoneVerified
{
    public function __construct(
        public readonly Authenticatable&Model $user,
        public readonly bool $registered,
    ) {}
}
