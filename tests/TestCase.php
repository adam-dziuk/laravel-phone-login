<?php

namespace AdamDziuk\PhoneLogin\Tests;

use AdamDziuk\PhoneLogin\PhoneLogin;
use AdamDziuk\PhoneLogin\PhoneLoginServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * The default setup: no guard of the app's own, so the package registers one
 * over its PhoneUser model, in a table named in the config.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PhoneLogin::loginView(fn (): string => 'login form');
        PhoneLogin::codeView(fn (string $phone): string => "code form for {$phone}");
    }

    protected function getPackageProviders($app): array
    {
        return [PhoneLoginServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('phone-login.users.table', 'sms_clients');
        $app['config']->set('phone-login.home', '/home');
        $app['config']->set('hashing.bcrypt.rounds', 4);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
