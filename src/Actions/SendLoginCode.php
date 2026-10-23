<?php

namespace AdamDziuk\PhoneLogin\Actions;

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;
use AdamDziuk\PhoneLogin\Models\PhoneLoginCode;
use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Support\Facades\Hash;

class SendLoginCode
{
    private const TTL_MINUTES = 5;

    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Returns the code it sent, so the caller can tie it to the login.
     *
     * With registration off, an unknown number returns null: the caller
     * gives the same response as for a known one, and no SMS is paid for. It
     * returns sooner, without hashing or sending, which timing can reveal.
     *
     * @throws SmsDeliveryFailed
     */
    public function handle(string $phone, ?string $ip = null): ?PhoneLoginCode
    {
        if (! config('phone-login.register_unknown') && ! $this->hasAccount($phone)) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneLoginCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $login = (new PhoneLoginCode)->forceFill([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'ip' => $ip,
        ]);

        $login->save();

        $this->sms->send($phone, PhoneLogin::message('sms', [
            'code' => $code,
            'minutes' => self::TTL_MINUTES,
        ]));

        return $login;
    }

    private function hasAccount(string $phone): bool
    {
        $model = PhoneLogin::userModel();

        return $model::query()->where('phone', $phone)->exists();
    }
}
