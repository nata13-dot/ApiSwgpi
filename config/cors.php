<?php

$frontendOrigins = array_filter(array_map(
    'trim',
    explode(',', env('FRONTEND_URLS', ''))
));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin requests can be executed
    | by this Laravel application. The values that you may set here are
    | discussed in the Mozilla web docs on CORS encountered here:
    |
    | https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS#The_HTTP_request_headers
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_merge([
        'http://127.0.0.1:3000',
        'http://localhost:3000',
        'http://127.0.0.1:3004',
        'http://localhost:3004',
        'http://127.0.0.1:3005',
        'http://localhost:3005',
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'http://127.0.0.1:8081',
        'http://localhost:8081',
        'http://127.0.0.1:19006',
        'http://localhost:19006',
        'http://127.0.0.1',
        'http://localhost',
        'https://127.0.0.1',
        'https://localhost',
        'http://localhost:8000',
        'http://localhost:8001',
        'capacitor://localhost',
        'ionic://localhost',
        'http://frontend_swgpi.test',
        'http://127.0.0.1:8000',
        'https://swgpi.online',
        'https://www.swgpi.online',
        'https://frontsgwpi-production.up.railway.app',
    ], $frontendOrigins))),

    'allowed_origins_patterns' => [
        '#^https://([a-z0-9-]+\.)?swgpi\.online$#i',
        '#^https?://localhost(?::\d+)?$#i',
        '#^https?://127\.0\.0\.1(?::\d+)?$#i',
    ],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Origin',
        'X-Requested-With',
        'X-SGPI-Remember',
    ],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,

];
