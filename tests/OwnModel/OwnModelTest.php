<?php

use AdamDziuk\PhoneLogin\PhoneLogin;
use AdamDziuk\PhoneLogin\Testing\FakeSmsSender;
use AdamDziuk\PhoneLogin\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->sms = FakeSmsSender::bind();
});

test('an app-defined guard is left untouched', function () {
    expect(config('auth.guards.web'))->toBe(['driver' => 'session', 'provider' => 'users'])
        ->and(config('auth.providers.phone_login_users'))->toBeNull()
        ->and(PhoneLogin::userModel())->toBe(User::class);
});

test('routes check the configured guard', function () {
    $this->actingAs(User::query()->forceCreate(['phone' => '+48060000002']), 'web')
        ->get(route('phone-login.login'))
        ->assertRedirect('/home');

    $this->post(route('phone-login.logout'))->assertRedirect(route('phone-login.login'));

    $this->assertGuest('web');
});

test('a new number signs up into the app own users table', function () {
    sendCode();
    verifyCode($this->sms->lastCode())->assertRedirect('/home');

    $user = User::query()->sole();

    expect($user->phone)->toBe('+48060000002')
        ->and(DB::table('sms_clients')->count())->toBe(0);
    $this->assertAuthenticatedAs($user, 'web');
});

test('createUsersUsing builds the model for a new number', function () {
    PhoneLogin::createUsersUsing(fn (string $phone): User => new User(['name' => 'Guest '.substr($phone, -3)]));

    sendCode();
    verifyCode($this->sms->lastCode());

    expect(User::query()->sole()->name)->toBe('Guest 002');
});

test('a model of the app own is texted once it uses the trait', function () {
    User::query()->forceCreate(['phone' => '+48060000002'])->sendMessage('Hello');

    expect($this->sms->sent)->toBe([['phone' => '+48060000002', 'message' => 'Hello']]);
});

test('a user without a number throws instead of skipping the message', function () {
    $user = User::query()->forceCreate(['name' => 'No phone']);

    expect(fn () => $user->sendMessage('Hello'))->toThrow(LogicException::class, 'no phone number in its [phone] column');

    expect($this->sms->sent)->toBeEmpty();
});
