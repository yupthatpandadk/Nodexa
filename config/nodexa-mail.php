<?php

return [
    'templates' => [
        'custom' => [
            'name' => 'Blank / Custom',
            'subject' => '',
            'message' => '',
        ],
        'welcome' => [
            'name' => 'Welcome to Nodexa',
            'subject' => 'Welcome to {{app_name}}',
            'message' => "Hi {{name}},\n\nWelcome to {{app_name}}. Your account is ready and you can now access your game server control panel.\n\nRegards,\nThe Nodexa Team",
        ],
        'maintenance' => [
            'name' => 'Planned maintenance',
            'subject' => 'Planned maintenance at {{app_name}}',
            'message' => "Hi {{name}},\n\nWe are carrying out planned maintenance on the Nodexa platform. During the maintenance window, some services may be temporarily unavailable.\n\nWe will work to restore full service as quickly as possible.\n\nRegards,\nThe Nodexa Team",
        ],
        'incident' => [
            'name' => 'Service incident',
            'subject' => 'Service status update from {{app_name}}',
            'message' => "Hi {{name}},\n\nWe are currently investigating a service disruption affecting parts of the platform. Our team is working on the issue and we will provide further information when available.\n\nRegards,\nThe Nodexa Team",
        ],
        'resolved' => [
            'name' => 'Incident resolved',
            'subject' => 'Service restored at {{app_name}}',
            'message' => "Hi {{name}},\n\nThe previously reported service disruption has been resolved and affected services are operating normally again.\n\nThank you for your patience.\n\nRegards,\nThe Nodexa Team",
        ],
        'server_ready' => [
            'name' => 'Server ready',
            'subject' => 'Your Nodexa server is ready',
            'message' => "Hi {{name}},\n\nYour game server has been provisioned and is ready to use. Sign in to the Nodexa control panel to manage the server.\n\nRegards,\nThe Nodexa Team",
        ],
        'security' => [
            'name' => 'Security notice',
            'subject' => 'Important security notice from {{app_name}}',
            'message' => "Hi {{name}},\n\nWe are contacting you with an important security notice regarding your Nodexa account.\n\nPlease review your account and contact support if you notice anything unexpected.\n\nRegards,\nThe Nodexa Team",
        ],
    ],
];
