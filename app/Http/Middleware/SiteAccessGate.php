<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

class SiteAccessGate
{
    private const PREFIX = 'nodexa::site_access.';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Countdown is self-expiring. As soon as the configured target time has
        // passed, disable it persistently and continue to the real website.
        // This check runs before Maintenance/bypass handling so the setting is
        // cleaned up even when an administrator is currently bypassing the page.
        if ($this->enabled('countdown_enabled') && $this->countdownExpired()) {
            $this->settings->set(self::PREFIX . 'countdown_enabled', '0');
        }

        if ($this->enabled('maintenance_enabled')) {
            if (!$this->canBypass($user, 'site_access.bypass_maintenance')) {
                return response()
                    ->view('site-access.maintenance', [
                        'title' => $this->settings->get(self::PREFIX . 'maintenance_title', 'Vi vedligeholder Nodexa'),
                        'message' => $this->settings->get(self::PREFIX . 'maintenance_message', 'Vi arbejder på platformen og er tilbage så hurtigt som muligt.'),
                    ], 503)
                    ->header('Retry-After', '300')
                    ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            }

            // Maintenance has priority over Countdown. An administrator with
            // maintenance bypass gets the real website even if Countdown is
            // also configured as enabled.
            return $next($request);
        }

        if ($this->enabled('countdown_enabled') && !$this->canBypass($user, 'site_access.bypass_countdown')) {
            return response()
                ->view('site-access.countdown', [
                    'title' => $this->settings->get(self::PREFIX . 'countdown_title', 'Vi er snart klar'),
                    'message' => $this->settings->get(self::PREFIX . 'countdown_message', 'Noget nyt er på vej. Vi åbner snart igen.'),
                    'target' => $this->settings->get(self::PREFIX . 'countdown_target', ''),
                    'serverNow' => CarbonImmutable::now()->toIso8601String(),
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return $next($request);
    }

    private function countdownExpired(): bool
    {
        $target = trim((string) $this->settings->get(self::PREFIX . 'countdown_target', ''));

        if ($target === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($target)->isPast();
        } catch (\Throwable) {
            // Keep an invalid target from taking the website offline or causing
            // request failures. An admin can correct it from Website Access.
            return false;
        }
    }

    private function enabled(string $key): bool
    {
        return filter_var(
            $this->settings->get(self::PREFIX . $key, '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function canBypass(mixed $user, string $permission): bool
    {
        return $user !== null
            && method_exists($user, 'hasPermission')
            && $user->hasPermission($permission);
    }
}
