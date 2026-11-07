<?php

return [
    'mail' => [
        'enabled' => env('SUPPORT_MAIL_ENABLED', false),
        // A mailbox that accepts plus addressing: support+<token>@example.com.
        'address' => env('SUPPORT_MAIL_ADDRESS'),
        'host' => env('SUPPORT_IMAP_HOST'),
        'port' => (int) env('SUPPORT_IMAP_PORT', 993),
        'username' => env('SUPPORT_IMAP_USERNAME'),
        'password' => env('SUPPORT_IMAP_PASSWORD'),
        'folder' => env('SUPPORT_IMAP_FOLDER', 'INBOX'),
        'max_bytes' => 25 * 1024 * 1024,
    ],
];
