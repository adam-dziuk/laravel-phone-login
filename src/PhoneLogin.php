<?php

namespace AdamDziuk\PhoneLogin;

use AdamDziuk\PhoneLogin\Concerns\EncryptsPhone;
use AdamDziuk\PhoneLogin\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

final class PhoneLogin
{
    /**
     * Render the phone number form. The closure receives the request.
     */
    public static function loginView(Closure $view): void
    {
        self::hooks()->loginView = $view;
    }

    /**
     * Render the code form. The closure receives the phone number waiting for
     * its code, then the request.
     */
    public static function codeView(Closure $view): void
    {
        self::hooks()->codeView = $view;
    }

    /**
     * Build the model for a number seen for the first time, for user tables
     * with required columns beyond the phone. Return it unsaved — the package
     * fills in the phone and saves it inside the login transaction.
     *
     * @param  Closure(string): (Authenticatable&Model)  $callback
     */
    public static function createUsersUsing(Closure $callback): void
    {
        self::hooks()->createUser = $callback;
    }

    /**
     * Extra validation for the phone number form, e.g. a terms checkbox.
     * Rules apply before any code is generated or sent. The phone field is
     * off limits: replacing its rules would drop the country allow-list.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    public static function sendCodeRules(array $rules, array $messages = []): void
    {
        if (array_key_exists('phone', $rules)) {
            throw new InvalidArgumentException('The phone field is validated by the package. Bind a subclass of '.PhoneNumber::class.' to change which numbers are accepted.');
        }

        self::hooks()->sendRules = $rules;
        self::hooks()->sendMessages = $messages;
    }

    /**
     * Laravel reads an empty guard name as the default guard, which would log
     * phone users into the staff guard under their own ids — and so into the
     * staff accounts that happen to share those ids.
     */
    public static function guard(): string
    {
        $guard = config('phone-login.guard');

        if (! is_string($guard) || ! $guard) {
            throw new LogicException('Set phone-login.guard to the name of a session guard.');
        }

        return $guard;
    }

    public static function auth(): StatefulGuard
    {
        $guard = app(AuthFactory::class)->guard(self::guard());

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('Phone login needs a session guard, ['.self::guard().'] is not one.');
        }

        return $guard;
    }

    /**
     * Full name of one of the package's routes, e.g. route('code').
     */
    public static function route(string $name): string
    {
        return config('phone-login.routes.name').$name;
    }

    /**
     * Table of the package's own PhoneUser model. Unused when the guard comes
     * from config/auth.php with a model of your own.
     */
    public static function usersTable(): string
    {
        $table = config('phone-login.users.table');

        return is_string($table) && $table !== '' ? $table : 'customers';
    }

    /**
     * The model behind the guard's user provider.
     *
     * @return class-string<Model>
     */
    public static function userModel(): string
    {
        $provider = config('auth.guards.'.self::guard().'.provider');
        $model = config("auth.providers.{$provider}.model");

        if (! is_string($model) || ! is_a($model, Model::class, true) || ! is_a($model, Authenticatable::class, true)) {
            throw new LogicException('The ['.self::guard().'] guard must use an Eloquent provider with an authenticatable model.');
        }

        // Without it the hash stays empty and every login would sign up anew.
        if (! in_array(EncryptsPhone::class, class_uses_recursive($model), true)) {
            throw new LogicException("[{$model}] must use the ".EncryptsPhone::class.' trait.');
        }

        return $model;
    }

    /**
     * What the users and codes tables find a number by. Keyed with APP_KEY,
     * so a leaked database alone cannot be turned back into numbers; rotating
     * APP_KEY changes every hash.
     */
    public static function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }

    public static function newUser(string $phone): Authenticatable&Model
    {
        $user = self::hooks()->createUser !== null
            ? (self::hooks()->createUser)($phone)
            : new (self::userModel());

        if (! $user instanceof Authenticatable || ! $user instanceof Model) {
            throw new LogicException('New phone login users must be authenticatable Eloquent models.');
        }

        return $user;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function message(string $key, array $replace = []): string
    {
        $line = __("phone-login::messages.{$key}", $replace);

        return is_string($line) ? $line : $key;
    }

    /**
     * @internal
     */
    public static function hooks(): Hooks
    {
        return app(Hooks::class);
    }
}
