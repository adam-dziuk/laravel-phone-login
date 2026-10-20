<?php

namespace AdamDziuk\PhoneLogin\Http\Controllers;

use AdamDziuk\PhoneLogin\Actions\SendLoginCode;
use AdamDziuk\PhoneLogin\Actions\VerifyLoginCode;
use AdamDziuk\PhoneLogin\Exceptions\InvalidLoginCode;
use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;
use AdamDziuk\PhoneLogin\Http\Requests\SendLoginCodeRequest;
use AdamDziuk\PhoneLogin\Http\Requests\VerifyLoginCodeRequest;
use AdamDziuk\PhoneLogin\PhoneLogin;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LogicException;

class PhoneLoginController extends Controller
{
    private const SESSION_KEY = 'phone_login';

    public function create(Request $request): mixed
    {
        return $this->render(PhoneLogin::hooks()->loginView, 'loginView', $request);
    }

    public function store(SendLoginCodeRequest $request, SendLoginCode $action): RedirectResponse
    {
        $phone = (string) $request->input('phone');

        try {
            $action->handle($phone, $request->ip());
        } catch (SmsDeliveryFailed) {
            return back()->withErrors(['phone' => PhoneLogin::message('delivery_failed')]);
        }

        $request->session()->put(self::SESSION_KEY, $phone);

        return to_route(PhoneLogin::route('code'));
    }

    public function code(Request $request): mixed
    {
        $phone = $request->session()->get(self::SESSION_KEY);

        if (is_string($phone)) {
            return $this->render(PhoneLogin::hooks()->codeView, 'codeView', $phone, $request);
        }

        return to_route(PhoneLogin::route('login'));
    }

    public function authenticate(VerifyLoginCodeRequest $request, VerifyLoginCode $action): RedirectResponse
    {
        $phone = $request->session()->get(self::SESSION_KEY);

        if (! is_string($phone)) {
            return to_route(PhoneLogin::route('login'));
        }

        try {
            $user = $action->handle($phone, (string) $request->input('code'));
        } catch (InvalidLoginCode) {
            return back()->withErrors(['code' => PhoneLogin::message('invalid_code')]);
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        PhoneLogin::auth()->login($user, remember: (bool) config('phone-login.remember'));

        return redirect($this->intendedUrl($request));
    }

    /**
     * Ends this guard's login only. The session is renewed, not invalidated:
     * flushing it would also sign out a login held by another guard in the
     * same browser, e.g. a staff account next to a phone one. Someone already
     * logged out, e.g. by an expired session, just goes back to the form.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $guard = PhoneLogin::auth();

        if ($guard->check()) {
            $guard->logout();

            $request->session()->regenerate(destroy: true);
        }

        return to_route(PhoneLogin::route('login'));
    }

    /**
     * The page that sent the guest to the login form, else home. For anything
     * but a GET, guest() stores the Referer instead — a HEAD request from
     * another site included — so only a URL under this app's root is followed.
     * The "/" or "?" after the root keeps out hosts that merely start like it.
     */
    private function intendedUrl(Request $request): string
    {
        $home = (string) config('phone-login.home');
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended)) {
            return $home;
        }

        $url = url($intended);

        // guest() builds its URL from the request; url() and route() use a
        // root the app may have forced, e.g. to https behind a proxy.
        foreach ([$request->root(), url('/')] as $root) {
            if ($url === $root || str_starts_with($url, $root.'/') || str_starts_with($url, $root.'?')) {
                return $url;
            }
        }

        return $home;
    }

    private function render(?Closure $view, string $hook, mixed ...$arguments): mixed
    {
        if ($view === null) {
            throw new LogicException("No phone login view registered. Call PhoneLogin::{$hook}() in a service provider.");
        }

        return $view(...$arguments);
    }
}
