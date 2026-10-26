<?php

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;
use AdamDziuk\PhoneLogin\Models\PhoneLoginCode;
use AdamDziuk\PhoneLogin\Models\PhoneUser;
use AdamDziuk\PhoneLogin\PhoneLogin;
use AdamDziuk\PhoneLogin\Testing\FakeSmsSender;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->sms = FakeSmsSender::bind();
});

test('stores a hash of the code, never the code itself', function () {
    sendCode()->assertRedirect(route('phone-login.code'));

    $record = PhoneLoginCode::query()->sole();
    $code = $this->sms->lastCode();

    expect($code)->toHaveLength(6)
        ->and($record->phone_hash)->toBe(PhoneLogin::phoneHash('+48060000002'))
        ->and($record->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $record->code_hash))->toBeTrue();
});

test('texts the code together with its lifetime', function () {
    sendCode();

    expect($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['phone'])->toBe('+48060000002')
        ->and($this->sms->sent[0]['message'])->toContain($this->sms->lastCode())
        ->and($this->sms->sent[0]['message'])->toContain('5 minutes');
});

test('normalises the number to E.164', function () {
    sendCode('060 000 002');

    expect(PhoneLoginCode::query()->sole()->phone_hash)->toBe(PhoneLogin::phoneHash('+48060000002'));
});

test('rejects input that is not a phone number', function () {
    sendCode('abc')->assertSessionHasErrors(['phone' => 'That does not look like a phone number.']);

    expect(PhoneLoginCode::query()->count())->toBe(0);
});

test('rejects an array instead of a number without a server error', function () {
    $this->post(route('phone-login.login.store'), ['phone' => ['060000002']])
        ->assertSessionHasErrors('phone');

    expect($this->sms->sent)->toBeEmpty();
});

test('refuses countries outside the allowed prefixes', function () {
    sendCode('+1 202 555 0143')->assertSessionHasErrors(['phone' => 'We cannot send a code to this number.']);

    expect($this->sms->sent)->toBeEmpty();
});

test('allows any country when allowed prefixes is null', function () {
    config()->set('phone-login.allowed_prefixes', null);

    sendCode('+1 202 555 0143')->assertRedirect(route('phone-login.code'));

    expect(PhoneLoginCode::query()->sole()->phone_hash)->toBe(PhoneLogin::phoneHash('+12025550143'));
});

test('a valid code signs an unknown number up and logs it in', function () {
    sendCode('+48069999999');

    verifyCode($this->sms->lastCode())->assertRedirect('/home');

    $user = PhoneUser::query()->where('phone_hash', PhoneLogin::phoneHash('+48069999999'))->sole();

    expect($user->phone_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user, 'phone');
});

test('an existing user logs into their own account', function () {
    $verifiedAt = now()->subYear()->startOfSecond();
    $user = PhoneUser::query()->forceCreate(['phone' => '+48060000002', 'phone_verified_at' => $verifiedAt]);

    sendCode();
    verifyCode($this->sms->lastCode());

    expect(PhoneUser::query()->count())->toBe(1)
        ->and($user->fresh()->phone_verified_at->equalTo($verifiedAt))->toBeTrue();
    $this->assertAuthenticatedAs($user->fresh(), 'phone');
});

test('logging in leads back to the page that sent the guest to the login form', function () {
    // Laravel builds the redirect to the staff login before any handler runs.
    Route::get('login', fn () => 'staff login form')->name('login');
    Route::middleware(['web', 'auth:phone'])->get('orders/{order}', fn (string $order) => "order {$order}");
    Route::getRoutes()->refreshNameLookups();
    // The handler the README puts in bootstrap/app.php.
    app(ExceptionHandler::class)->renderable(fn (AuthenticationException $exception) => redirect()->guest(route('phone-login.login')));

    $this->get('/orders/123')->assertRedirect(route('phone-login.login'));

    sendCode();
    verifyCode($this->sms->lastCode())->assertRedirect('/orders/123');

    $this->get('/orders/123')->assertOk()->assertSee('order 123');
});

test('the page waiting for the login survives a wrong code', function () {
    $this->withSession(['url.intended' => url('/orders/123')]);

    sendCode();
    verifyCode('000000')->assertSessionHasErrors('code');

    verifyCode($this->sms->lastCode())->assertRedirect('/orders/123');
});

test('a page of the app is followed however it was stored', function (string $intended) {
    $this->withSession(['url.intended' => $intended]);

    sendCode();
    verifyCode($this->sms->lastCode())->assertRedirect(url($intended));
})->with([
    'a path' => '/orders/123',
    'the home page with a query' => 'http://localhost?tab=orders',
]);

test('a page outside the app is not followed after the login', function (string $intended) {
    $this->withSession(['url.intended' => $intended]);

    sendCode();
    verifyCode($this->sms->lastCode())->assertRedirect('/home');
})->with([
    'another site' => 'https://evil.example/phish',
    'a host that merely starts like this one' => 'http://localhost.evil.example/orders/123',
    'a protocol-relative URL' => '//evil.example/orders/123',
]);

test('a HEAD request from another site does not choose the page after the login', function () {
    Route::get('login', fn () => 'staff login form')->name('login');
    Route::middleware(['web', 'auth:phone'])->get('orders/{order}', fn (string $order) => "order {$order}");
    Route::getRoutes()->refreshNameLookups();

    // For anything but a GET, guest() stores the Referer as the page to return to.
    $this->call('HEAD', '/orders/123', server: ['HTTP_REFERER' => 'https://evil.example/phish']);

    sendCode();
    verifyCode($this->sms->lastCode())->assertRedirect('/home');
});

test('a wrong code does not log in and counts an attempt', function () {
    sendCode();

    verifyCode('000000')->assertSessionHasErrors(['code' => 'The code is invalid, has expired or has already been used.']);

    expect(PhoneLoginCode::query()->sole()->attempts)->toBe(1);
    $this->assertGuest('phone');
});

test('a code dies once its attempts are used up', function () {
    sendCode();
    $code = $this->sms->lastCode();

    PhoneLoginCode::query()->sole()->forceFill(['attempts' => 5])->save();

    verifyCode($code)->assertSessionHasErrors('code');

    $this->assertGuest('phone');
});

test('attempts used up by parallel requests stop a code read before them', function () {
    sendCode();
    $code = $this->sms->lastCode();

    // Parallel wrong guesses land between this request reading the code and checking it.
    PhoneLoginCode::retrieved(fn (PhoneLoginCode $login) => PhoneLoginCode::query()
        ->whereKey($login->getKey())
        ->update(['attempts' => 5]));

    verifyCode($code)->assertSessionHasErrors('code');

    $this->assertGuest('phone');
});

test('an expired code does not log in', function () {
    sendCode();
    $code = $this->sms->lastCode();

    $this->travel(6)->minutes();

    verifyCode($code)->assertSessionHasErrors('code');

    $this->assertGuest('phone');
});

test('a code cannot be used twice', function () {
    sendCode();
    $code = $this->sms->lastCode();

    verifyCode($code);
    $this->post(route('phone-login.logout'));

    sendCode();
    verifyCode($code)->assertSessionHasErrors('code');

    $this->assertGuest('phone');
});

test('a new code invalidates the previous one', function () {
    sendCode();
    $first = $this->sms->lastCode();

    sendCode();

    verifyCode($first)->assertSessionHasErrors('code');

    $this->assertGuest('phone');
});

test('wrong guesses from another session leave the code of this one alone', function () {
    // Another browser starts a login for the same number first...
    sendCode();
    $elsewhere = session()->all();

    // ...then the customer asks for a code of their own.
    session()->flush();
    sendCode();
    $code = $this->sms->lastCode();
    $mine = session()->all();

    session()->flush();
    session()->put($elsewhere);

    foreach (range(1, 5) as $guess) {
        verifyCode('000000')->assertSessionHasErrors('code');
    }

    session()->flush();
    session()->put($mine);

    verifyCode($code)->assertRedirect('/home');
});

test('a session that holds no code of its own cannot verify one', function () {
    sendCode();
    $code = $this->sms->lastCode();

    // As a session started before codes were tied to it would look.
    session()->forget('phone_login_code');

    verifyCode($code)->assertSessionHasErrors('code');

    $this->assertGuest('phone');
});

test('sending is throttled per number, whatever the spelling', function () {
    sendCode('+48060000002');
    sendCode('060 000 002');
    sendCode('0048060000002');

    sendCode('060-000-002')->assertTooManyRequests();
});

test('a number gets at most ten codes a day', function () {
    foreach (range(1, 10) as $hour) {
        sendCode()->assertRedirect(route('phone-login.code'));
        $this->travel(1)->hours();
    }

    sendCode()->assertTooManyRequests();

    $this->travel(15)->hours();

    sendCode()->assertRedirect(route('phone-login.code'));
});

test('the whole app sends at most codes_per_hour codes an hour', function () {
    config()->set('phone-login.codes_per_hour', 2);

    sendCode('+48060000002')->assertRedirect(route('phone-login.code'));
    sendCode('+48060000003')->assertRedirect(route('phone-login.code'));
    sendCode('+48060000004')->assertTooManyRequests();

    $this->travel(1)->hours();

    sendCode('+48060000004')->assertRedirect(route('phone-login.code'));
});

test('a failed delivery shows an error instead of a server error', function () {
    $this->app->instance(SmsSender::class, new class implements SmsSender
    {
        public function send(string $phone, string $message): void
        {
            throw SmsDeliveryFailed::to($phone, new RuntimeException('gateway down'));
        }
    });

    sendCode()->assertSessionHasErrors(['phone' => 'We could not send the code. Please try again in a moment.']);

    $this->assertGuest('phone');
});

test('the code screen shows the number waiting for its code', function () {
    sendCode();

    $this->get(route('phone-login.code'))->assertOk()->assertSee('code form for +48060000002');
});

test('the code screen without a started login goes back to the start', function () {
    $this->get(route('phone-login.code'))->assertRedirect(route('phone-login.login'));
});

test('logging out ends the session', function () {
    sendCode();
    verifyCode($this->sms->lastCode());

    $this->post(route('phone-login.logout'))->assertRedirect(route('phone-login.login'));

    $this->assertGuest('phone');
});

test('logging out leaves a login held by another guard alone', function () {
    $staffLogin = Auth::guard('web')->getName();

    sendCode();
    verifyCode($this->sms->lastCode());

    $this->withSession([$staffLogin => 1])
        ->post(route('phone-login.logout'))
        ->assertSessionHas($staffLogin, 1);

    $this->assertGuest('phone');
});

test('logging out without a phone login goes back to the phone login form', function () {
    Route::get('login', fn () => 'staff login form')->name('login');
    Route::getRoutes()->refreshNameLookups();

    $this->post(route('phone-login.logout'))->assertRedirect(route('phone-login.login'));
});

test('logging out without a phone login leaves a staff login alone', function () {
    $staffLogin = Auth::guard('web')->getName();

    $this->withSession([$staffLogin => 1])
        ->post(route('phone-login.logout'))
        ->assertSessionHas($staffLogin, 1);
});

test('a logged in user is sent home from the login form, not to the staff dashboard', function () {
    Route::get('dashboard', fn () => 'staff dashboard')->name('dashboard');
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs(PhoneUser::query()->forceCreate(['phone' => '+48060000002']), 'phone')
        ->get(route('phone-login.login'))
        ->assertRedirect('/home');
});

test('a staff login does not keep anyone away from the phone login form', function () {
    $this->withSession([Auth::guard('web')->getName() => 1])
        ->get(route('phone-login.login'))
        ->assertOk();
});
