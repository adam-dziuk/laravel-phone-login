<?php

namespace AdamDziuk\PhoneLogin\Tests;

use AdamDziuk\PhoneLogin\Tests\Fixtures\User;

/**
 * An app that logs phone users into a guard and model of its own, defined in
 * config/auth.php — the package must leave both alone.
 */
abstract class OwnModelTestCase extends TestCase
{
    /**
     * Runs where a real app loads config/*.php: before providers register,
     * which is when the package decides whether to add a guard of its own.
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('phone-login.guard', 'web');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }
}
