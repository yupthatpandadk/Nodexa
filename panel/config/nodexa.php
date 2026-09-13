<?php

return [
    'storefront_domain' => env('NODEXA_STOREFRONT_DOMAIN'),

    'discord' => [
        'enabled' => env('NODEXA_DISCORD_LOGIN_ENABLED', false),
        'client_id' => env('NODEXA_DISCORD_CLIENT_ID'),
        'client_secret' => env('NODEXA_DISCORD_CLIENT_SECRET'),
        'redirect_uri' => env('NODEXA_DISCORD_REDIRECT_URI'),
        'after_login' => env('NODEXA_DISCORD_AFTER_LOGIN', '/client'),
    ],
];
