<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KomoPay Backoffice API
    |--------------------------------------------------------------------------
    |
    | Configuration for the Backoffice API the UI consumes. The same
    | application can run against the real API in local/production, or
    | against an in-memory mock implementation for development and demos.
    |
    | Toggle the data source with KOMOPAY_USE_MOCK_API.
    */

    'base_url' => env('KOMOPAY_API_BASE_URL', 'http://localhost:8080'),

    'use_mock_api' => filter_var(env('KOMOPAY_USE_MOCK_API', true), FILTER_VALIDATE_BOOLEAN),

    'timeout' => (int) env('KOMOPAY_API_TIMEOUT', 15),

    'token' => env('KOMOPAY_API_TOKEN'),

    'prefix' => env('KOMOPAY_API_PREFIX', '/api/v1/backoffice'),
];
