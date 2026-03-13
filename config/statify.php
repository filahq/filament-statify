<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The authentication method for the Statify API.
    |
    | Supported: "token", "sanctum"
    |
    | - "token": Uses a static token from STATIFY_TOKEN env var.
    | - "sanctum": Uses Laravel Sanctum personal access tokens.
    |
    */
    'guard' => env('STATIFY_GUARD', 'token'),

    /*
    |--------------------------------------------------------------------------
    | API Token (for "token" guard)
    |--------------------------------------------------------------------------
    |
    | The static token used to authenticate API requests.
    | If null and guard is "token", the API is open (no auth).
    |
    */
    'token' => env('STATIFY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to cache the stats response.
    | Set to 0 to disable caching entirely.
    |
    */
    'cache_ttl' => env('STATIFY_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all cache keys used by Statify.
    | Change this if multiple apps share the same cache store.
    |
    */
    'cache_prefix' => env('STATIFY_CACHE_PREFIX', 'statify'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for the Statify API routes.
    | Set to null or false (STATIFY_PREFIX=) to disable route registration.
    |
    */
    'prefix' => env('STATIFY_PREFIX', 'api/statify'),

];
