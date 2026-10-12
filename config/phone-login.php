<?php

use AdamDziuk\PhoneLogin\Models\PhoneUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The session guard people are logged into. When config/auth.php already
    | defines it, its user provider decides the model and the "users" keys
    | below are ignored. When it does not, the package registers the guard
    | itself, backed by the model and table from "users". Keep it apart from
    | the staff guard: with "register_unknown" on, anyone with a phone would
    | get an account in the staff table.
    |
    */

    'guard' => 'phone',

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    |
    | Where phone users — verified numbers — live when the package registers
    | the guard. Rename the table before publishing and running the
    | phone-login-users-migration if "customers" is taken. Extend PhoneUser to
    | add relations and put the subclass in "model".
    |
    */

    'users' => [
        'model' => PhoneUser::class,
        'table' => 'customers',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Columns
    |--------------------------------------------------------------------------
    |
    | "phone" holds the E.164 number and is the lookup key. "verified_at" is
    | stamped on the first verified login and never moved afterwards; set it
    | to null when the model has no such column.
    |
    */

    'columns' => [
        'phone' => 'phone',
        'verified_at' => 'phone_verified_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | Signing up and signing in are one path: an unknown number that verifies
    | a code gets an account. Turned off, an unknown number gets the very same
    | response but no SMS. The response comes faster, though, so timing can
    | still tell known numbers apart — see "Limitations" in the README.
    |
    */

    'register_unknown' => true,

    'remember' => true,

    /*
    |--------------------------------------------------------------------------
    | Home
    |--------------------------------------------------------------------------
    |
    | Where people land after logging in, and where the login form sends
    | someone already logged in on this guard.
    |
    */

    'home' => '/',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Registers GET/POST {prefix}/login, GET/POST {prefix}/code and POST
    | {prefix}/logout, named {name}login, {name}login.store, {name}code,
    | {name}code.store and {name}logout.
    |
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'phone',
        'name' => 'phone-login.',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Codes
    |--------------------------------------------------------------------------
    |
    | Six digits, stored as a hash. "ttl" is in minutes. A code that has used
    | up its attempts stays dead even when the right digits come in later.
    |
    */

    'code' => [
        'ttl' => 5,
        'max_attempts' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttling
    |--------------------------------------------------------------------------
    |
    | Sending is limited per hour, verifying per minute. The per-phone bucket
    | is keyed by the normalised number, so "060 000 002" and "+48060000002"
    | share it.
    |
    */

    'throttle' => [
        'send_per_phone' => 3,
        'send_per_ip' => 15,
        'verify_per_ip' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone Numbers
    |--------------------------------------------------------------------------
    |
    | Input of exactly "national_length" digits gets "default_prefix" in front.
    | Codes only go to numbers starting with one of "allowed_prefixes" (country
    | calling codes, digits only) — keep this list short: an open list invites
    | SMS pumping to premium-rate numbers abroad. null allows any country.
    |
    */

    'phone' => [
        'default_prefix' => '48',
        'national_length' => 9,
        'allowed_prefixes' => ['48'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Codes keep the phone number and IP address, so they are pruned after this
    | many days. Schedule `model:prune` with the PhoneLoginCode model.
    |
    */

    'prune_after_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "log", "smsapi". Bind your own implementation of
    | AdamDziuk\PhoneLogin\Contracts\SmsSender for anything else.
    |
    | SMSAPI: "service" is "pl" (smsapi.pl) or "com" (smsapi.com). With
    | "no_unicode" a message outside GSM-7 is rejected instead of billed as
    | UCS-2 — keep translated SMS texts free of diacritics.
    |
    */

    'sms' => [
        'driver' => env('PHONE_LOGIN_SMS_DRIVER', 'log'),

        'smsapi' => [
            'token' => env('SMSAPI_TOKEN'),
            'from' => env('SMSAPI_FROM'),
            'service' => 'pl',
            'no_unicode' => true,
            'fast' => true,
            'log_channel' => null,
        ],
    ],

];
