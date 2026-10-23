# Laravel Phone Login

1. Install the package.

2. Publish the config. Every key is described in `config/phone-login.php`:

   ```bash
   php artisan vendor:publish --tag=phone-login-config
   ```

3. Create the tables. Phone users get a `customers` table of their own; if that name doesn't fit, change `users.table` before you migrate:

   ```bash
   php artisan vendor:publish --tag=phone-login-migrations
   php artisan vendor:publish --tag=phone-login-users-migration
   php artisan migrate
   ```

   Already have a customer model? Skip the users migration, add a session
   guard for the model to `config/auth.php` (not the staff guard) and set
   `guard` to its name. Its table needs fields: `phone` (unique `varchar(20)`),
   `phone_verified_at` (nullable timestamp) and `remember_token`. Fill any
   other required columns in a service provider:

   ```php
   use AdamDziuk\PhoneLogin\PhoneLogin;

   PhoneLogin::createUsersUsing(fn (string $phone) => (new Customer)->forceFill(['name' => 'Guest']));
   ```

4. Register the views in a service provider:

   ```php
   use AdamDziuk\PhoneLogin\PhoneLogin;

   PhoneLogin::loginView(fn () => view('auth.phone-login'));
   PhoneLogin::codeView(fn (string $phone) => view('auth.phone-code', ['phone' => $phone]));
   ```

   The login form posts `phone` to `route('phone-login.login.store')`, the
   code form posts `code` to `route('phone-login.code.store')`, and logging out
   is a POST to `route('phone-login.logout')`.

5. Send guests of your `auth:phone` pages to the phone login, in
   `bootstrap/app.php`. They come back to the page after logging in. With no
   `login` route of your own:

   ```php
   use AdamDziuk\PhoneLogin\PhoneLogin;

   ->withMiddleware(function (Middleware $middleware): void {
       $middleware->redirectGuestsTo(fn () => route(PhoneLogin::route('login')));
   })
   ```

   Next to a staff `login` route, send only phone guests instead:

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

6. Send the codes through SMSAPI. Until then, the `log` driver writes them to
   the app's log:

   ```bash
   composer require "smsapi/php-client:^4.0"
   ```

   ```dotenv
   PHONE_LOGIN_SMS_DRIVER=smsapi
   SMSAPI_TOKEN=your-api-token
   SMSAPI_FROM=YourCompany
   ```

   For an smsapi.com account, set `sms.smsapi.service` to `com`.

7. Prune old login codes, in `routes/console.php`:

   ```php
   use AdamDziuk\PhoneLogin\Models\PhoneLoginCode;
   use Illuminate\Support\Facades\Schedule;

   Schedule::command('model:prune', ['--model' => [PhoneLoginCode::class]])->daily();
   ```
