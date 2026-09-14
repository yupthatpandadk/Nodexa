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

    'discord_bot' => [
        'enabled' => env('NODEXA_DISCORD_BOT_ENABLED', false),
        'token' => env('NODEXA_DISCORD_BOT_TOKEN'),
        'client_id' => env('NODEXA_DISCORD_BOT_CLIENT_ID'),
        'guild_id' => env('NODEXA_DISCORD_BOT_GUILD_ID'),
        'status_channel_id' => env('NODEXA_DISCORD_BOT_STATUS_CHANNEL_ID'),
        'auto_role_id' => env('NODEXA_DISCORD_BOT_AUTO_ROLE_ID'),
        'presence' => env('NODEXA_DISCORD_BOT_PRESENCE', 'Nodexa Hosting'),
    ],
];
