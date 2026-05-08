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

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://127.0.0.1:3000',
        'http://localhost:3000',
        'http://localhost:8000',
        'http://localhost:8001',
        'http://frontend_swgpi.test',
        'http://127.0.0.1:8000'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
