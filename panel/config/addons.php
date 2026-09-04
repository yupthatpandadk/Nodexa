<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nodexa Addon Catalog
    |--------------------------------------------------------------------------
    |
    | Addons are discovered from local manifest folders. The web UI can only
    | install packages that are already present in this trusted catalog path;
    | it does not accept arbitrary PHP uploads or shell commands.
    |
    */
    'catalog_path' => env('NODEXA_ADDON_CATALOG', base_path('addons')),

    /*
    |--------------------------------------------------------------------------
    | Legacy lifecycle hooks
    |--------------------------------------------------------------------------
    |
    | Kept disabled by default. Legacy hook scripts can execute with elevated
    | privileges during upgrades and should only ever be enabled deliberately.
    |
    */
    'hooks_enabled' => env('ADDONS_HOOKS_ENABLED', false),
];
