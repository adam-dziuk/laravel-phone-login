<?php

namespace AdamDziuk\PhoneLogin\Actions;

use AdamDziuk\PhoneLogin\Events\PhoneVerified;
use AdamDziuk\PhoneLogin\Exceptions\InvalidLoginCode;
use AdamDziuk\PhoneLogin\Models\PhoneLoginCode;
use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyLoginCode
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Signing up and signing in are the same path — an unknown number that
     * verifies gets an account, unless registration is turned off.
     *
     * With $codeId only that code is checked, else the newest live code for
     * the number.
     *
     * @throws InvalidLoginCode
     */
    public function handle(string $phone, string $code, ?int $codeId = null): Authenticatable&Model
    {
        $login = PhoneLoginCode::query()
            ->where('phone', $phone)
            ->when($codeId !== null, fn ($query) => $query->whereKey($codeId))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        $usable = $login instanceof PhoneLoginCode
            && $this->claimAttempt($login)
            && Hash::check($code, $login->code_hash);

        if ($usable) {
            return DB::transaction(fn (): Authenticatable&Model => $this->consume($login, $phone));
        }

        throw new InvalidLoginCode;
    }

    /**
     * Counts the attempt before the hash is checked, in one conditional
     * update. Checking a count read earlier would let parallel guesses all
     * slip under the limit before any of them is recorded.
     */
    private function claimAttempt(PhoneLoginCode $login): bool
    {
        return PhoneLoginCode::query()
            ->whereKey($login->getKey())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->increment('attempts') === 1;
    }

    /**
     * Two requests can both pass the hash check; the row lock lets only the
     * first one consume the code.
     *
     * @throws InvalidLoginCode
     */
    private function consume(PhoneLoginCode $login, string $phone): Authenticatable&Model
    {
        $locked = PhoneLoginCode::query()->whereKey($login->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->consumed_at !== null) {
            throw new InvalidLoginCode;
        }

        $locked->forceFill(['consumed_at' => now()])->save();

        return $this->user($phone);
    }

    /**
     * @throws InvalidLoginCode
     */
    private function user(string $phone): Authenticatable&Model
    {
        $model = PhoneLogin::userModel();
        $user = $model::query()->where('phone', $phone)->first();
        $registered = $user === null;

        if ($user === null && ! config('phone-login.register_unknown')) {
            throw new InvalidLoginCode;
        }

        $user = $user instanceof Authenticatable ? $user : PhoneLogin::newUser($phone);

        $user->forceFill([
            'phone' => $phone,
            'phone_verified_at' => $user->getAttribute('phone_verified_at') ?? now(),
        ])->save();

        event(new PhoneVerified($user, $registered));

        return $user;
    }
}
