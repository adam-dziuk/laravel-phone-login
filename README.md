# Laravel Phone Login

Passwordless login with a phone number and a six-digit SMS code. Signing up and
signing in are the same path: an unknown number that verifies its code gets an
account. The package handles routes, controllers, validation, throttling and
code storage, and ships no UI — you register your own views, the way Fortify
does it.

What it takes care of:

- codes stored as hashes (the app's hasher, bcrypt by default), never in
  plain text;
- a new code invalidates the previous one; a code dies after 5 wrong attempts
  or 5 minutes;
- a row lock, so two parallel requests cannot spend the same code;
- throttling per number (normalised, so every spelling shares one bucket) and
  per IP;
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

Then pick where phone users live.

**A table of their own (default).** With no `phone` guard in
`config/auth.php`, the package registers one itself, backed by its
`PhoneUser` model and a `customers` table — one row per verified number. If
that name is taken, change `users.table` in `config/phone-login.php` before
publishing the migration:

```bash
php artisan vendor:publish --tag=phone-login-users-migration
php artisan migrate
```

To add relations, extend `PhoneUser` and put the subclass in `users.model`.
A `$table` set explicitly on the subclass wins over the config.

**A model of your own.** Define the guard and its provider in
`config/auth.php` and set `guard` to its name — the package then leaves
both alone and ignores the `users` keys. The model needs a unique `phone`
column (E.164, `varchar(20)`) and, optionally, a nullable
`phone_verified_at` timestamp. Column names are configurable. Skip the users
migration and run `php artisan migrate`. Do not point `guard` at the staff
guard: with `register_unknown` on, anyone with a phone would get a row in the
staff table and a staff session.

Schedule pruning — `model:prune` only looks into `app/Models` by default:

```php
use AdamDziuk\PhoneLogin\Models\PhoneLoginCode;

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
| `home` | `/` | redirect after login |
| `register_unknown` | `true` | `false` = login only, unknown numbers get no SMS |
| `phone.allowed_prefixes` | `['48']` | country calling codes that may receive codes; `null` = any |
| `sms.driver` | `log` | `log` or `smsapi` |

The per-IP limits count `$request->ip()`. Behind a load balancer or CDN,
configure trusted proxies: without them every visitor shares the proxy's
address and one busy hour locks everyone out, while trusting `*` where clients
can reach the app directly lets them pick their own address in
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

The login form posts `phone`; the code form posts `code`. Errors come back on
those two keys.

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
            return redirect()->route(PhoneLogin::route('login'));
        }
    });
})
```

A plain redirect, not `redirect()->guest()`: the phone login always lands on
`home`, never on the intended URL, because that session key is shared with the
staff login. One stored here would only greet the next staff login.

## Hooks

Extra validation on the phone form, checked before any code is made:

```php
PhoneLogin::sendCodeRules(
    ['terms' => ['accepted']],
    ['terms.accepted' => 'Please accept the terms.'],
);
```

Building new users when your table has required columns beyond the phone —
return the model unsaved, the package fills in the phone and saves it:

```php
PhoneLogin::createUsersUsing(fn (string $phone) => new User(['name' => 'Guest']));
```

After a successful verification the package dispatches
`AdamDziuk\PhoneLogin\Events\PhoneVerified` (`$user`, `$registered`). It fires
**inside the login transaction**: a synchronous listener commits or rolls back
together with the login, so if it throws, the code stays unused and no user is
created.

## SMS

`log` writes the message to the default log channel — handy locally. `smsapi`
needs `smsapi/php-client` and `SMSAPI_TOKEN` (optionally `SMSAPI_FROM`). For any
other provider, bind your own implementation:

```php
use AdamDziuk\PhoneLogin\Contracts\SmsSender;

$this->app->bind(SmsSender::class, TwilioSender::class);
```

`send()` runs synchronously and throws `SmsDeliveryFailed` on failure, so the
form can show an error instead of a 500.

## Testing your app

```php
use AdamDziuk\PhoneLogin\Testing\FakeSmsSender;

$sms = FakeSmsSender::bind();

$this->post(route('phone-login.login.store'), ['phone' => '060 000 002']);
$this->post(route('phone-login.code.store'), ['code' => $sms->lastCode()]);
```

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
