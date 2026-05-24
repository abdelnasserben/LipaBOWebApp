<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KomoPay Backoffice API
    |--------------------------------------------------------------------------
    |
    | Configuration for the real Backoffice API consumed by the UI.
    */

    'base_url' => env('KOMOPAY_API_BASE_URL', 'http://localhost:8080'),

    'timeout' => (int) env('KOMOPAY_API_TIMEOUT', 15),

    'token' => env('KOMOPAY_API_TOKEN'),

    'prefix' => env('KOMOPAY_API_PREFIX', '/api/v1/backoffice'),

    // Version segment for shared (non-backoffice) endpoints such as the
    // notifications inbox served at /api/v1/notifications/** (spec §5.22).
    'api_version' => env('KOMOPAY_API_VERSION', 'api/v1'),
];
