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
    | This is the "default" key: the one resolved by `app(VcrClient::class)`
    | and by the `VcrAm` facade. Use a production key here in production,
    | a sandbox key during development.
    |
    */

    'api_key' => env('VCR_AM_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox API Key
    |--------------------------------------------------------------------------
    |
    | Optional second key, scoped to a sandbox VCR. When set, a parallel
    | `VcrClient` is registered in the container under `vcr-am.sandbox` and
    | exposed via `VcrAm::sandbox()` — convenient for keeping a production
    | client AND a sandbox client wired side-by-side, e.g. to run E2E
    | smoke tests against the real sandbox endpoint without touching prod.
    |
    | Leave null if you don't need a second channel.
    |
    */

    'sandbox_api_key' => env('VCR_AM_SANDBOX_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Override the API endpoint. Leave null to use the SDK default
    | (https://vcr.am/api/v1). Useful for staging environments or local
    | mock servers during development. Applies to BOTH the default and the
    | sandbox client.
    |
    */

    'base_url' => env('VCR_AM_BASE_URL'),

];
