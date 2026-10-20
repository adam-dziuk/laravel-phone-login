# Laravel Phone Login

Passwordless login with a phone number and a six-digit SMS code. Signing up and
signing in are the same path: an unknown number that verifies its code gets an
account. The package handles routes, controllers, validation, throttling and
code storage, and ships no UI — you register your own views, the way Fortify
does it.

Requires PHP 8.3+ and Laravel 13.

What it takes care of:

- codes stored as hashes (the app's hasher, bcrypt by default), never in
  plain text;
- a new code invalidates the previous one; a code dies after 5 wrong attempts
  or 5 minutes (both configurable);
- a row lock, so two parallel requests cannot spend the same code;
- throttling per IP and per number — normalised, so every spelling shares one
  bucket, and capped at 3 codes an hour and 10 a day;
- an allow-list of country calling codes against SMS pumping;
- pruning of stored numbers and IP addresses after 30 days;
- an optional "login only" mode in which unknown numbers get no SMS and the
  same response as known ones (but see [Limitations](#limitations)).

## Installation

```bash
composer require adam-dziuk/laravel-phone-login
php artisan vendor:publish --tag=phone-login-config
php artisan vendor:publish --tag=phone-login-migrations
```

Phone users get a table of their own, `customers`, kept apart from your staff
accounts: one row per verified number, behind the package's `PhoneUser` model
and `phone` guard. If that name is taken, change `users.table` in
`config/phone-login.php` before you migrate:

```bash
php artisan vendor:publish --tag=phone-login-users-migration
php artisan migrate
```

To add relations, extend `PhoneUser` (say, as `Customer`) and put the subclass
in `users.model`. A `$table` set explicitly on the subclass wins over the
config.

**Already have a customer model?** Skip the users migration. Add a session
guard with an Eloquent provider for the model to `config/auth.php`, set
`guard` in `config/phone-login.php` to that guard's name, and run
`php artisan migrate`. The package leaves your guard alone and ignores
`users.table` and `users.model`. The model's table needs:

- `phone` — unique `varchar(20)`, holds E.164 numbers;
- `phone_verified_at` — nullable timestamp, or set `columns.verified_at` to
  `null`;
- `remember_token`.

`columns` renames the first two. Other required columns need a value from
[`createUsersUsing`](#hooks). Do not use your staff guard here: with
`register_unknown` on, anyone with a phone would get a staff account.

Schedule pruning in `routes/console.php` — `model:prune` only looks into
`app/Models` by default:

```php
use AdamDziuk\PhoneLogin\Models\PhoneLoginCode;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [PhoneLoginCode::class]])->daily();
```

## Configuration

`config/phone-login.php` documents every key. The ones you will touch first:

| key | default | |
|---|---|---|
| `guard` | `phone` | session guard to log into; registered by the package unless `config/auth.php` defines it |
| `users.table` | `customers` | table of verified numbers, behind the package's own `PhoneUser` model |
| `users.model` | `PhoneUser` | model behind the package's guard |
| `routes.prefix` / `routes.name` | `phone` / `phone-login.` | URL prefix and route name prefix |
| `home` | `/` | redirect after login when no page is waiting for it |
| `register_unknown` | `true` | `false` = login only, unknown numbers get no SMS |
| `remember` | `true` | remember-me cookie; needs a `remember_token` column |
| `phone.default_prefix` / `phone.national_length` | `48` / `9` | country code put in front of a number with exactly that many digits |
| `phone.allowed_prefixes` | `['48']` | country calling codes that may receive codes; `null` = any |
| `sms.driver` | `log` | `log` or `smsapi`; set with `PHONE_LOGIN_SMS_DRIVER` |

A number gets at most 3 codes an hour (`throttle.send_per_phone`) and 10 a day
(`throttle.send_per_phone_per_day`), which bounds how many guesses anyone can
make at it. A config published before the daily cap existed has no such key
and gets 10 as well; set the key to `null` to lift the cap.

The per-IP limits count `$request->ip()`. Behind a load balancer or CDN,
configure trusted proxies — without them every visitor shares the proxy's
address, and one busy hour locks everyone out. Do not trust `*` where clients
can reach the app directly: they could pick their own address in
`X-Forwarded-For`.

Routes, with the default prefix and names:

| method | path | name |
|---|---|---|
| GET | `/phone/login` | `phone-login.login` |
| POST | `/phone/login` | `phone-login.login.store` |
| GET | `/phone/code` | `phone-login.code` |
| POST | `/phone/code` | `phone-login.code.store` |
| POST | `/phone/logout` | `phone-login.logout` |

## Views

Register them in a service provider. Anything a route may return works — a
Blade view, an Inertia page, a Livewire component:

```php
use AdamDziuk\PhoneLogin\PhoneLogin;
use Inertia\Inertia;

PhoneLogin::loginView(fn () => Inertia::render('auth/phone-login'));
PhoneLogin::codeView(fn (string $phone) => Inertia::render('auth/phone-code', ['phone' => $phone]));
```

The login form posts `phone`; the code form posts `code`. Validation errors
come back on those two keys, plus any fields you add with
[`sendCodeRules`](#hooks). Going over a rate limit returns a 429 response, not
a form error; to show it on the form, add to `bootstrap/app.php`:

```php
use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
        if ($request->routeIs(PhoneLogin::route('*'))) {
            $field = $request->routeIs(PhoneLogin::route('code.store')) ? 'code' : 'phone';

            return back()->withErrors([$field => 'Too many attempts. Try again later.']);
        }
    });
})
```

## Next to staff accounts

Phone users get a guard of their own, so a staff login on `web` and a phone
login can share one browser. The package's routes look only at its guard: a
logged in phone user who opens the login form goes to `home`, and logging out
ends the phone login alone — the session is renewed, not invalidated, so the
staff login survives. A staff logout that invalidates the session, as
Fortify's and Filament's do, drops the phone login from the session too; with
`remember` on, the phone guard's own cookie signs it straight back in.

Your own pages behind `auth:phone` need one more step. Laravel sends every
guest to the `login` route — the staff form — so send guests of the phone
guard to the phone form instead, in `bootstrap/app.php`:

```php
use AdamDziuk\PhoneLogin\PhoneLogin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (AuthenticationException $exception, Request $request) {
        if (! $request->expectsJson() && in_array(PhoneLogin::guard(), $exception->guards(), true)) {
            return redirect()->guest(route(PhoneLogin::route('login')));
        }
    });
})
```

`redirect()->guest()` remembers the page, so logging in leads back to it — say,
an order linked in an SMS — and to `home` when no page is waiting. Only a page
of this app counts: for anything but a GET, Laravel remembers the `Referer`,
which may point at another site. Laravel keeps that page under one session key
for all guards: in a browser shared with a staff login, the next login on
either side takes whatever page the other one left behind.

Laravel works out the staff redirect, `route('login')`, before this handler
runs. Without a `login` route — say, with phone users only — point every guest
at the phone form instead and skip the handler; Laravel then remembers the page
itself:

```php
use AdamDziuk\PhoneLogin\PhoneLogin;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn () => route(PhoneLogin::route('login')));
})
```

## Hooks

Extra validation on the phone form, checked before any code is made:

```php
PhoneLogin::sendCodeRules(
    ['terms' => ['accepted']],
    ['terms.accepted' => 'Please accept the terms.'],
);
```

Building new users when their table has required columns beyond the phone —
return the model unsaved, the package fills in the phone and saves it:

```php
PhoneLogin::createUsersUsing(fn (string $phone) => (new Customer)->forceFill(['name' => 'Guest']));
```

After a successful verification the package dispatches
`AdamDziuk\PhoneLogin\Events\PhoneVerified` (`$user`, `$registered`), before
logging the user in. It fires **inside the login transaction**: a synchronous
listener commits or rolls back together with it, so if it throws, the code
stays unused and no user is created.

## SMS

`log`, the default driver, writes each message to the app's log. Use it for
local work only: the log then holds every login code in plain text. For
SMSAPI:

```bash
composer require "smsapi/php-client:^4.0"
```

```dotenv
PHONE_LOGIN_SMS_DRIVER=smsapi
SMSAPI_TOKEN=your-api-token
SMSAPI_FROM=YourBrand
```

`SMSAPI_FROM` is optional: a sender name registered with SMSAPI. An
smsapi.com account (rather than smsapi.pl) also needs `sms.smsapi.service` set
to `com`.

For any other provider, bind your own implementation:

```php
use AdamDziuk\PhoneLogin\Contracts\SmsSender;

$this->app->bind(SmsSender::class, TwilioSender::class);
```

`send()` runs synchronously and throws `SmsDeliveryFailed` on failure, so the
form can show an error instead of a 500.

The SMS text and the form errors come in English and Polish. To change them,
publish them to `lang/vendor/phone-login`:

```bash
php artisan vendor:publish --tag=phone-login-lang
```

With SMSAPI, keep the SMS in GSM-7 — no `ą`, `ę`, `ó` and the like.
`sms.smsapi.no_unicode` (on by default) makes SMSAPI reject anything else, and
the form then shows a delivery error.

## Messages

Phone users can be texted through the same SMS driver as the login codes, e.g.
from an admin panel:

```php
$customer->sendMessage('Your order is ready: '.route('orders.show', $order));
```

`PhoneUser` and its subclasses can do it out of the box. A model of your own
needs the trait:

```php
use AdamDziuk\PhoneLogin\Concerns\ReceivesSms;

class Customer extends Authenticatable
{
    use ReceivesSms;
}
```

The number comes from the `columns.phone` column; a model without one throws a
`LogicException` rather than skipping the message. The SMS goes out at once,
with no queue and no retries, and a failed delivery throws `SmsDeliveryFailed`
for your code to handle. A link to a page behind `auth:phone` brings someone
logged out back to it after the login — see
[Next to staff accounts](#next-to-staff-accounts).

**With SMSAPI, strip the diacritics first.** `no_unicode` makes SMSAPI reject
any text outside GSM-7, so "Zamówienie zostało wysłane" fails with
`SmsDeliveryFailed`. `Str::ascii()` turns it into "Zamowienie zostalo wyslane":

```php
use Illuminate\Support\Str;

$customer->sendMessage(Str::ascii($text));
```

## Testing your app

```php
use AdamDziuk\PhoneLogin\Testing\FakeSmsSender;

$sms = FakeSmsSender::bind();

$this->post(route('phone-login.login.store'), ['phone' => '060 000 002']);
$this->post(route('phone-login.code.store'), ['code' => $sms->lastCode()]);
```

The fake catches [messages](#messages) too: each one lands in `$sms->sent` as
`['phone' => …, 'message' => …]`.

## Limitations

Number handling is deliberately simple: strip non-digits, prepend the default
country code to a bare national number, accept 11–15 digits. Bind a subclass of
`AdamDziuk\PhoneLogin\Support\PhoneNumber` if you need per-country validation
(e.g. with libphonenumber).

Login-only mode hides whether a number has an account from the response, not
from a stopwatch. A known number waits while its code is hashed and the SMS goes
out; an unknown one is answered at once, and only a known number can ever see
the delivery error. Someone timing the form can tell the two apart, so do not
rely on this mode alone when the list of your users must stay secret.

## Developing

```bash
composer install
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
