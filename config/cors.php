<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://frontend-rentus-pruebas-production.up.railway.app',
        'http://localhost:5173',
        'http://localhost:4173',
    ],

    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.railway\.app$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
