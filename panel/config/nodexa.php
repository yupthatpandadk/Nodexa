<?php
return [
    'database_host' => env('NODEXA_DATABASE_HOST', '127.0.0.1'),
    'database_port' => (int) env('NODEXA_DATABASE_PORT', 3306),
    'phpmyadmin_url' => env('NODEXA_PHPMYADMIN_URL', '/phpmyadmin/'),
    'phpmyadmin_signon_session' => env('NODEXA_PHPMYADMIN_SIGNON_SESSION', 'NodexaSignon'),

    'update_repository' => env('NODEXA_UPDATE_REPOSITORY', 'yupthatpandadk/Nodexa'),
    'update_branch' => env('NODEXA_UPDATE_BRANCH', 'main'),
];
