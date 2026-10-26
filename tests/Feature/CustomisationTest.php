<?php

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Events\PhoneVerified;
use AdamDziuk\PhoneLogin\Hooks;
use AdamDziuk\PhoneLogin\Models\PhoneLoginCode;
use AdamDziuk\PhoneLogin\Models\PhoneUser;
use AdamDziuk\PhoneLogin\PhoneLogin;
use AdamDziuk\PhoneLogin\Sms\LogSender;
use AdamDziuk\PhoneLogin\Testing\FakeSmsSender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

test('with registration off an unknown number gets the same answer but no SMS', function () {
    config()->set('phone-login.register_unknown', false);
    $sms = FakeSmsSender::bind();

    sendCode('+48069999999')->assertRedirect(route('phone-login.code'));

    expect($sms->sent)->toBeEmpty()
        ->and(PhoneLoginCode::query()->count())->toBe(0);

    verifyCode('123456')->assertSessionHasErrors('code');

    expect(PhoneUser::query()->count())->toBe(0);
    $this->assertGuest('phone');
});

test('with registration off a known number still logs in', function () {
    config()->set('phone-login.register_unknown', false);
    $sms = FakeSmsSender::bind();
    $user = PhoneUser::query()->forceCreate(['phone' => '+48060000002']);

    sendCode();
    verifyCode($sms->lastCode())->assertRedirect('/home');

    $this->assertAuthenticatedAs($user->fresh(), 'phone');
});

test('sendCodeRules validate the form before any code is made', function () {
    $sms = FakeSmsSender::bind();
    PhoneLogin::sendCodeRules(['terms' => ['accepted']], ['terms.accepted' => 'Accept the terms first.']);

    sendCode()->assertSessionHasErrors(['terms' => 'Accept the terms first.']);

    expect($sms->sent)->toBeEmpty()
        ->and(PhoneLoginCode::query()->count())->toBe(0);

    sendCode(extra: ['terms' => 'on'])->assertRedirect(route('phone-login.code'));
});

test('sendCodeRules cannot replace the rules that hold the country allow-list', function () {
    expect(fn () => PhoneLogin::sendCodeRules(['phone' => ['nullable']]))
        ->toThrow(InvalidArgumentException::class, 'PhoneNumber');
});

test('the allow-list holds even when extra rules reach the hooks directly', function () {
    $sms = FakeSmsSender::bind();
    app(Hooks::class)->sendRules = ['phone' => ['nullable']];

    sendCode('+1 202 555 0143')->assertSessionHasErrors('phone');

    expect($sms->sent)->toBeEmpty();
});

test('an empty guard name fails loudly instead of falling back to the default guard', function (mixed $guard) {
    config()->set('phone-login.guard', $guard);

    expect(fn () => PhoneLogin::guard())->toThrow(LogicException::class, 'phone-login.guard');
})->with([null, '', '0']);

test('PhoneVerified says whether the user has just registered', function () {
    $sms = FakeSmsSender::bind();
    $events = [];
    Event::listen(PhoneVerified::class, function (PhoneVerified $event) use (&$events) {
        $events[] = $event;
    });

    sendCode();
    verifyCode($sms->lastCode());
    $this->post(route('phone-login.logout'));
    sendCode();
    verifyCode($sms->lastCode());

    expect($events)->toHaveCount(2)
        ->and($events[0]->registered)->toBeTrue()
        ->and($events[1]->registered)->toBeFalse()
        ->and($events[1]->user->is(PhoneUser::query()->sole()))->toBeTrue();
});

test('a listener that throws rolls the whole login back', function () {
    $sms = FakeSmsSender::bind();
    Event::listen(PhoneVerified::class, fn () => throw new RuntimeException('listener failed'));

    sendCode();
    $this->withoutExceptionHandling();

    expect(fn () => verifyCode($sms->lastCode()))->toThrow(RuntimeException::class, 'listener failed');

    expect(PhoneLoginCode::query()->sole()->consumed_at)->toBeNull()
        ->and(PhoneUser::query()->count())->toBe(0);
    $this->assertGuest('phone');
});

test('a missing view explains how to register one', function () {
    app(Hooks::class)->loginView = null;
    $this->withoutExceptionHandling();

    expect(fn () => $this->get(route('phone-login.login')))->toThrow(LogicException::class, 'PhoneLogin::loginView()');
});

test('codes are pruned after the retention window', function () {
    $code = fn (int $days) => (new PhoneLoginCode)->forceFill([
        'phone_hash' => PhoneLogin::phoneHash('+48060000002'),
        'code_hash' => 'x',
        'expires_at' => now()->subDays($days),
        'created_at' => now()->subDays($days),
    ])->save();

    $code(31);
    $code(29);

    $this->artisan('model:prune', ['--model' => [PhoneLoginCode::class]])->assertSuccessful();

    expect(PhoneLoginCode::query()->count())->toBe(1);
});

test('the log driver writes the message to the log', function () {
    Log::shouldReceive('info')->once()->withArgs(fn (string $line): bool => str_contains($line, '+48060000002'));

    expect(app(SmsSender::class))->toBeInstanceOf(LogSender::class);

    sendCode();
});

test('an unknown SMS driver fails loudly', function () {
    config()->set('phone-login.sms.driver', 'carrier-pigeon');

    expect(fn () => app(SmsSender::class))->toThrow(InvalidArgumentException::class, 'carrier-pigeon');
});
