<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hashids Configuration
    |--------------------------------------------------------------------------
    |
    | Here are the settings that control the Hashids setup and usage in the panel.
    | APP_KEY is used as the default salt so fresh Nodexa installations work
    | without requiring an additional HASHIDS_SALT environment variable.
    |
    */
    'salt' => env('HASHIDS_SALT', env('APP_KEY', 'nodexa-hashids')),
    'length' => env('HASHIDS_LENGTH', 8),
    'alphabet' => env('HASHIDS_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'),
];
