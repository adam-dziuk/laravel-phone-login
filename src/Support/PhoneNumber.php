<?php

namespace AdamDziuk\PhoneLogin\Support;

/**
 * Deliberately small: digits in, E.164 out. Apps that need per-country
 * validation can bind their own subclass in the container.
 */
class PhoneNumber
{
    /**
     * @param  list<string>|null  $allowedPrefixes
     */
    public function __construct(
        private readonly string $defaultPrefix = '48',
        private readonly int $nationalLength = 9,
        private readonly ?array $allowedPrefixes = null,
    ) {}

    public static function fromConfig(): self
    {
        $allowed = config('phone-login.phone.allowed_prefixes');

        return new self(
            (string) config('phone-login.phone.default_prefix'),
            (int) config('phone-login.phone.national_length'),
            is_array($allowed) ? array_values(array_map(strval(...), $allowed)) : null,
        );
    }

    /**
     * Normalise user input to E.164, assuming the default country when the
     * input is exactly one national number long. Returns null when the input
     * cannot be a phone number.
     */
    public function normalise(string $input): ?string
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        if ($digits === '') {
            return null;
        }

        $digits = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;

        if (strlen($digits) === $this->nationalLength) {
            $digits = $this->defaultPrefix.$digits;
        }

        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return '+'.$digits;
        }

        return null;
    }

    /**
     * Country calling codes are prefix-free, so a plain prefix match is exact.
     */
    public function isAllowed(string $phone): bool
    {
        if ($this->allowedPrefixes === null) {
            return true;
        }

        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($phone, '+'.$prefix)) {
                return true;
            }
        }

        return false;
    }
}
