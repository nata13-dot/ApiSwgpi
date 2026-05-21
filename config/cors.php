<?php

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

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://frontsgwpi-production.up.railway.app',
        'http://localhost:3000',
        'http://localhost:3004',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3004',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\.up\.railway\.app$#i',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
