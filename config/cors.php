<?php

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

$allowedOrigins = array_values(array_filter(array_map(
    fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

if ($allowedOrigins === []) {
    $allowedOrigins = env('APP_ENV') === 'production'
        ? [$appUrl]
        : array_unique(array_filter([
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'https://bulletinlab.test',
            $appUrl,
        ]));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'X-Auth-Mode', 'X-Etablissement', 'Authorization', 'Accept', 'Origin'],
    'exposed_headers' => ['X-Total-Count'],
    'max_age' => 0,
    'supports_credentials' => true,
];
