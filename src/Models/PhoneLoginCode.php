<?php

namespace AdamDziuk\PhoneLogin\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Keyed by phone number rather than user id: on a first login there is no
 * user yet, and creating one before the code verifies would let anyone fill
 * the users table with random numbers.
 *
 * @property int $id
 * @property string $phone
 * @property string $code_hash
 * @property CarbonInterface $expires_at
 * @property int $attempts
 * @property CarbonInterface|null $consumed_at
 * @property string|null $ip
 * @property CarbonInterface|null $created_at
 */
class PhoneLoginCode extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $hidden = ['code_hash'];

    /**
     * The phone number and IP address stay only as long as they help defend
     * the login against abuse.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('phone-login.prune_after_days')));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
