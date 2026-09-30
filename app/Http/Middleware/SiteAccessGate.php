<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
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
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        return $next($request);
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
