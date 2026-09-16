<?php

namespace Pterodactyl\Providers;

use Pterodactyl\Extensions\Hashids;
use Illuminate\Support\ServiceProvider;
use Pterodactyl\Contracts\Extensions\HashidsInterface;

class HashidsServiceProvider extends ServiceProvider
{
    /**
     * Register the ability to use Hashids.
     */
    public function register(): void
    {
        $this->app->singleton(HashidsInterface::class, function () {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $this->app['config'];

            // config()->get() returns null when HASHIDS_SALT exists in the
            // configuration but has no value. The default argument therefore
            // does not protect Hashids' strictly typed constructor. Prefer an
            // explicit salt, then APP_KEY, and finally a stable Nodexa fallback
            // so a missing optional environment variable can never crash a
            // request during application bootstrap.
            $salt = $config->get('hashids.salt');
            if (!is_string($salt) || trim($salt) === '') {
                $salt = $config->get('app.key');
            }
            if (!is_string($salt) || trim($salt) === '') {
                $salt = 'nodexa-hashids';
            }

            return new Hashids(
                $salt,
                (int) $config->get('hashids.length', 8),
                (string) $config->get('hashids.alphabet', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890')
            );
        });

        $this->app->alias(HashidsInterface::class, 'hashids');
    }
}
