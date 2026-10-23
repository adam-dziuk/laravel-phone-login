<?php

namespace AdamDziuk\PhoneLogin;

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Sms\LogSender;
use AdamDziuk\PhoneLogin\Sms\SmsapiSender;
use AdamDziuk\PhoneLogin\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class PhoneLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/phone-login.php', 'phone-login');
        $this->registerGuard();

        $this->app->singleton(Hooks::class);
        $this->app->bind(PhoneNumber::class, fn (): PhoneNumber => new PhoneNumber(
            allowedPrefixes: config('phone-login.allowed_prefixes'),
        ));
        $this->app->bind(SmsSender::class, fn (): SmsSender => $this->smsSender());
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'phone-login');

        $this->configureRateLimiting();

        $this->loadRoutesFrom(__DIR__.'/../routes/phone-login.php');

        if ($this->app->runningInConsole()) {
            $this->configurePublishing();
        }
    }

    /**
     * A guard defined in config/auth.php is the app's to own. Without one,
     * the package adds a session guard over its own users table — so phone
     * users get a table of their own instead of landing among staff accounts.
     */
    private function registerGuard(): void
    {
        $guard = PhoneLogin::guard();

        if (config("auth.guards.{$guard}") !== null) {
            return;
        }

        config([
            "auth.guards.{$guard}" => ['driver' => 'session', 'provider' => 'phone_login_users'],
            'auth.providers.phone_login_users' => ['driver' => 'eloquent', 'model' => config('phone-login.users.model')],
        ]);
    }

    private function smsSender(): SmsSender
    {
        $driver = (string) config('phone-login.sms.driver');

        return match ($driver) {
            'log' => new LogSender,
            'smsapi' => SmsapiSender::fromConfig((array) config('phone-login.sms.smsapi')),
            default => throw new InvalidArgumentException("Unsupported phone login SMS driver [{$driver}]."),
        };
    }

    /**
     * Throttling runs before the form request normalises the number, so the
     * per-phone key normalises it here — otherwise every spelling of the same
     * number ("060000002", "+48 060 000 002", "0048…") would get its own bucket.
     * Ten codes a day bound the guesses anyone can make at one number.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('phone-login-send', function (Request $request): array {
            $input = $request->input('phone');
            $phone = is_string($input) ? ($this->app->make(PhoneNumber::class)->normalise($input) ?? $input) : '';

            $limits = [
                Limit::perHour(3)->by('phone:'.$phone),
                Limit::perDay(10)->by('phone-day:'.$phone),
                Limit::perHour(15)->by('ip:'.$request->ip()),
            ];

            // Counts attempts, not delivered SMS.
            $perHour = config('phone-login.codes_per_hour');

            if ($perHour !== null) {
                $limits[] = Limit::perHour((int) $perHour)->by('global');
            }

            return $limits;
        });

        RateLimiter::for('phone-login-verify', fn (Request $request): Limit => Limit::perMinute(6)->by('ip:'.$request->ip()));
    }

    private function configurePublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/phone-login.php' => config_path('phone-login.php'),
        ], 'phone-login-config');

        $codes = '2026_10_02_000000_create_phone_login_codes_table.php';
        $users = '2026_10_02_000001_create_phone_login_users_table.php';

        $this->publishesMigrations([
            __DIR__."/../database/migrations/{$codes}" => database_path("migrations/{$codes}"),
        ], 'phone-login-migrations');

        // Separate tag: apps logging into a users table of their own skip it.
        $this->publishesMigrations([
            __DIR__."/../database/migrations/{$users}" => database_path("migrations/{$users}"),
        ], 'phone-login-users-migration');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/phone-login'),
        ], 'phone-login-lang');
    }
}
