<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your VCR.AM API key. Generate one at https://vcr.am after signing up.
    | Required — the package will throw a RuntimeException if missing.
    |
    */

    'api_key' => env('VCR_AM_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Override the API endpoint. Leave null to use the SDK default
    | (https://vcr.am/api/v1). Useful for staging environments or local
    | mock servers during development.
    |
    */

    'base_url' => env('VCR_AM_BASE_URL'),

];
