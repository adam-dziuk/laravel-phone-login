<?php

use AdamDziuk\PhoneLogin\Models\PhoneUser;

return [

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The session guard phone users log into. One defined in config/auth.php
    | is left alone and "users" is ignored; otherwise the package registers
    | it. Never the staff guard: anyone with a phone would get an account.
    |
    */

    'guard' => 'phone',

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    |
    | Model and table behind the package's own guard. Rename the table before
    | running the users migration; extend PhoneUser to add relations.
    |
    */

    'users' => [
        'model' => PhoneUser::class,
        'table' => 'customers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | false: unknown numbers get the same response, but no SMS and no account.
    |
    */

    'register_unknown' => true,

    /*
    |--------------------------------------------------------------------------
    | Remember Me
    |--------------------------------------------------------------------------
    |
    | Needs a remember_token column; the package's own table has one.
    |
    */

    'remember' => true,

    /*
    |--------------------------------------------------------------------------
    | Home
    |--------------------------------------------------------------------------
    |
    | Where people land after logging in when no page is waiting for them.
    |
    */

    'home' => '/',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | URL prefix, route name prefix and middleware of the package's routes.
    | The middleware must start a session ("web" does).
    |
    */

    'routes' => [
        'prefix' => 'phone',
        'name' => 'phone-login.',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Prefixes
    |--------------------------------------------------------------------------
    |
    | Country calling codes, digits only, whose numbers may get codes; null
    | allows any. Keep the list short: an open one invites SMS pumping to
    | premium-rate numbers abroad.
    |
    */

    'allowed_prefixes' => ['48'],

    /*
    |--------------------------------------------------------------------------
    | Codes Per Hour
    |--------------------------------------------------------------------------
    |
    | Codes the whole app may send in an hour, so a flood spread over many
    | numbers cannot drain the SMS balance. Set it above your busiest hour;
    | null leaves it off.
    |
    */

    'codes_per_hour' => null,

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | Drivers: "log" and "smsapi". Bind your own SmsSender for anything else.
    | "service" is "pl" for smsapi.pl accounts, "com" for smsapi.com ones.
    |
    */

    'sms' => [
        'driver' => env('PHONE_LOGIN_SMS_DRIVER', 'log'),

        'smsapi' => [
            'token' => env('SMSAPI_TOKEN'),
            'from' => env('SMSAPI_FROM'),
            'service' => 'pl',
        ],
    ],

];
