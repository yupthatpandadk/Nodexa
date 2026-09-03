<?php

namespace Pterodactyl\Providers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Database;
use Pterodactyl\Enum\ResourceLimit;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\Http\Middleware\TrimStrings;
use Pterodactyl\Http\Middleware\AdminAuthenticate;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected const FILE_PATH_REGEX = '/^\/api\/client\/servers\/([a-z0-9-]{36})\/files(\/?$|\/(.)*$)/i';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Disable trimming string values when requesting file information — it isn't helpful
        // and messes up the ability to actually open a directory that ends with a space.
        TrimStrings::skipWhen(function (Request $request) {
            return preg_match(self::FILE_PATH_REGEX, $request->getPathInfo()) === 1;
        });

        // This is needed to make use of the "resolveRouteBinding" functionality in the
        // model. Without it you'll never trigger that logic flow thus resulting in a 404
        // error because we request databases with a HashID, and not with a normal ID.
        Route::model('database', Database::class);

        $this->routes(function () {
            Route::middleware('web')->group(function () {
                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class])
                    ->group(base_path('routes/base.php'));

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, AdminAuthenticate::class])
                    ->prefix('/admin')
                    ->group(base_path('routes/admin.php'));

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, AdminAuthenticate::class])
                    ->prefix('/admin')
                    ->group(base_path('routes/admin-nodexa.php'));

                Route::middleware('guest')->prefix('/auth')->group(base_path('routes/auth.php'));
            });

            Route::middleware(['api', RequireTwoFactorAuthentication::class])->group(function () {
                Route::middleware(['application-api', 'throttle:api.application'])
                    ->prefix('/api/application')
                    ->scopeBindings()
                    ->group(base_path('routes/api-application.php'));

                Route::middleware(['client-api', 'throttle:api.client'])
                    ->prefix('/api/client')
                    ->scopeBindings()
                    ->group(base_path('routes/api-client.php'));
            });

            Route::middleware('daemon')
                ->prefix('/api/remote')
                ->scopeBindings()
                ->group(base_path('routes/api-remote.php'));
        });
    }

    /**
     * Configure the throttles for the application.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('authentication', function (Request $request) {
            return Limit::perMinute(config('http.rate_limit.authentication'))->by($request->ip());
        });

        RateLimiter::for('api.application', function (Request $request) {
            return Limit::perMinute(config('http.rate_limit.application'))
                ->by($request->user()?->uuid ?? $request->ip());
        });

        RateLimiter::for('api.client', function (Request $request) {
            return Limit::perMinute(config('http.rate_limit.client'))
                ->by($request->user()?->uuid ?? $request->ip());
        });
    }
}
