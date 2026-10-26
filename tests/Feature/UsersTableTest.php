<?php

use AdamDziuk\PhoneLogin\Models\PhoneUser;
use AdamDziuk\PhoneLogin\PhoneLogin;
use AdamDziuk\PhoneLogin\Testing\FakeSmsSender;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('without a guard of its own the app gets one from the package', function () {
    expect(config('auth.guards.phone'))->toBe(['driver' => 'session', 'provider' => 'phone_login_users'])
        ->and(config('auth.providers.phone_login_users.model'))->toBe(PhoneUser::class)
        ->and(PhoneLogin::userModel())->toBe(PhoneUser::class);
});

test('a user model without the EncryptsPhone trait is refused', function () {
    config()->set('auth.providers.phone_login_users.model', User::class);

    expect(fn () => PhoneLogin::userModel())->toThrow(LogicException::class, 'EncryptsPhone');
});

test('the users table is named in the config', function () {
    expect((new PhoneUser)->getTable())->toBe('sms_clients')
        ->and(Schema::hasTable('sms_clients'))->toBeTrue()
        ->and(Schema::hasColumns('sms_clients', ['phone', 'phone_hash', 'phone_verified_at', 'remember_token']))->toBeTrue();
});

test('people who log in land in the named table', function () {
    $sms = FakeSmsSender::bind();

    sendCode();
    verifyCode($sms->lastCode());

    $row = DB::table('sms_clients')->sole();

    expect(Crypt::decryptString($row->phone))->toBe('+48060000002')
        ->and($row->phone_hash)->toBe(PhoneLogin::phoneHash('+48060000002'))
        ->and($row->phone_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs(PhoneUser::query()->sole(), 'phone');
});

test('a subclass with its own table wins over the config', function () {
    $model = new class extends PhoneUser
    {
        protected $table = 'clients';
    };

    expect($model->getTable())->toBe('clients');
});

test('the table is called customers unless configured otherwise', function () {
    $defaults = require __DIR__.'/../../config/phone-login.php';

    expect($defaults['users']['table'])->toBe('customers');
});

test('an empty table name falls back to customers', function () {
    config()->set('phone-login.users.table', '');

    expect((new PhoneUser)->getTable())->toBe('customers');
});
