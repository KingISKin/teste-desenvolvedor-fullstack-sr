<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | The API is consumed exclusively through Bearer personal access tokens,
    | so no first-party domain receives cookie-based (stateful) auth.
    |
    */

    'stateful' => [],

    // Token-only API: the SPA cookie endpoint (/sanctum/csrf-cookie) is not registered.
    'routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Empty on purpose: Sanctum must not fall back to the session guard. Only
    | the "Authorization: Bearer <token>" header authenticates a request.
    |
    */

    'guard' => [],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Tokens expire after this many minutes (default 8 hours). The login
    | action also stamps "expires_at" on each token so the value is visible
    | to clients and enforced even if this setting changes later.
    |
    */

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 480),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
