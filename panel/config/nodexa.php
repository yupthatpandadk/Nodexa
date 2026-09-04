<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nodexa Storefront Domain
    |--------------------------------------------------------------------------
    |
    | This value is cached with the rest of Laravel's configuration so the
    | storefront host routes continue to work after `config:cache` and
    | `route:cache` have been generated in production.
    |
    */
    'storefront_domain' => env('NODEXA_STOREFRONT_DOMAIN'),
];
