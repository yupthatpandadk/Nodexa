<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\Events\Failed;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

abstract class AbstractLoginController extends Controller
{
    use AuthenticatesUsers;

    protected AuthManager $auth;

    protected int $lockoutTime;
    protected int $maxLoginAttempts;
    protected string $redirectTo = '/';

    public function __construct()
    {
        $this->lockoutTime = config('auth.lockout.time');
        $this->maxLoginAttempts = config('auth.lockout.attempts');
        $this->auth = Container::getInstance()->make(AuthManager::class);
    }

    protected function sendFailedLoginResponse(Request $request, ?Authenticatable $user = null, ?string $message = null)
    {
        $this->incrementLoginAttempts($request);
        $this->fireFailedLoginEvent($user, [
            $this->getField($request->input('user')) => $request->input('user'),
        ]);

        if ($request->route()->named('auth.login-checkpoint')) {
            throw new DisplayException($message ?? trans('auth.two_factor.checkpoint_failed'));
        }

        throw new DisplayException(trans('auth.failed'));
    }

    protected function sendLoginResponse(User $user, Request $request): JsonResponse
    {
        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();
        $this->clearLoginAttempts($request);
        $this->auth->guard()->login($user, true);
        Event::dispatch(new DirectLogin($user, true));

        return new JsonResponse([
            'data' => [
                'complete' => true,
                'intended' => $this->storefrontUrl(),
                'user' => $user->toVueObject(),
            ],
        ]);
    }

    /**
     * Log the user out and return them to the public Nodexa storefront.
     */
    public function logout(Request $request)
    {
        $this->auth->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($this->storefrontUrl());
    }

    protected function storefrontUrl(): string
    {
        $configured = trim((string) config('nodexa.storefront_domain', ''));

        if ($configured !== '') {
            if (!preg_match('#^https?://#i', $configured)) {
                $configured = 'https://' . $configured;
            }

            return rtrim($configured, '/') . '/';
        }

        $panelUrl = (string) config('app.url', '/');
        $parts = parse_url($panelUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host !== '' && str_starts_with($host, 'panel.')) {
            $scheme = (string) ($parts['scheme'] ?? 'https');
            return $scheme . '://' . substr($host, 6) . '/';
        }

        return '/';
    }

    protected function getField(?string $input = null): string
    {
        return ($input && str_contains($input, '@')) ? 'email' : 'username';
    }

    protected function fireFailedLoginEvent(?Authenticatable $user = null, array $credentials = [])
    {
        Event::dispatch(new Failed('auth', $user, $credentials));
    }
}
