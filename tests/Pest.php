<?php

use AdamDziuk\PhoneLogin\Tests\OwnModelTestCase;
use AdamDziuk\PhoneLogin\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(OwnModelTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('OwnModel');

/**
 * Test numbers must never belong to anyone. Polish ones start with 0 after
 * +48: the national numbering plan (Dz.U. 2024 poz. 1862) only gives out
 * numbers starting with 1–9. Foreign ones come from ranges reserved for
 * fiction, e.g. +1 NXX 555 0100–0199.
 *
 * @param  array<string, mixed>  $extra
 */
function sendCode(string $phone = '+48060000002', array $extra = []): TestResponse
{
    return test()->post(route('phone-login.login.store'), ['phone' => $phone, ...$extra]);
}

function verifyCode(?string $code): TestResponse
{
    return test()->post(route('phone-login.code.store'), ['code' => $code]);
}
