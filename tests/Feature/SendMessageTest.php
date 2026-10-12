<?php

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;
use AdamDziuk\PhoneLogin\Models\PhoneUser;
use AdamDziuk\PhoneLogin\Testing\FakeSmsSender;

test('a phone user is texted at their own number', function () {
    $sms = FakeSmsSender::bind();
    $user = PhoneUser::query()->forceCreate(['phone' => '+48060000002']);

    $user->sendMessage('Your order is ready: https://example.com/orders/123');

    expect($sms->sent)->toBe([
        ['phone' => '+48060000002', 'message' => 'Your order is ready: https://example.com/orders/123'],
    ]);
});

test('the number comes from the configured phone column', function () {
    config()->set('phone-login.columns.phone', 'mobile');
    $sms = FakeSmsSender::bind();

    (new PhoneUser)->forceFill(['mobile' => '+48060000003'])->sendMessage('Hello');

    expect($sms->sent[0]['phone'])->toBe('+48060000003');
});

test('a failed delivery reaches the caller', function () {
    $this->app->instance(SmsSender::class, new class implements SmsSender
    {
        public function send(string $phone, string $message): void
        {
            throw SmsDeliveryFailed::to($phone, new RuntimeException('gateway down'));
        }
    });

    $user = PhoneUser::query()->forceCreate(['phone' => '+48060000002']);

    expect(fn () => $user->sendMessage('Hello'))->toThrow(SmsDeliveryFailed::class);
});
