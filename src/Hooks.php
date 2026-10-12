<?php

namespace AdamDziuk\PhoneLogin;

use Closure;

/**
 * Customisations registered through PhoneLogin. Lives in the container rather
 * than in static properties, so every test gets a clean slate.
 *
 * @internal
 */
final class Hooks
{
    public ?Closure $loginView = null;

    public ?Closure $codeView = null;

    public ?Closure $createUser = null;

    /** @var array<string, mixed> */
    public array $sendRules = [];

    /** @var array<string, string> */
    public array $sendMessages = [];
}
