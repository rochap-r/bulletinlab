<?php

$appUrlHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

$allowedOrigins = array_values(array_filter(array_map(
    fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

if ($allowedOrigins === []) {
    $allowedOrigins = array_unique(array_filter([
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'https://'.$appUrlHost,
        'http://'.$appUrlHost,
    ]));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'Origin'],
    'exposed_headers' => ['X-Total-Count'],
    'max_age' => 0,
    'supports_credentials' => false,
];
