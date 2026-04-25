<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // En dev, le front peut tourner sur n'importe quel port (Vite) et sur 127.0.0.1.
    // Pour restreindre en prod, définir `CORS_ALLOWED_ORIGINS` (CSV d'origins exactes).
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => env('CORS_ALLOWED_ORIGINS', null)
        ? []
        : [
            '/^https?:\/\/localhost(:\d+)?$/',
            '/^https?:\/\/127\.0\.0\.1(:\d+)?$/',
        ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
