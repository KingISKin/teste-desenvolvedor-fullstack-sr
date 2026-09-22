<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| The SPA and the API are served from the same origin (nginx proxies /api),
| so no cross-origin access is needed and none is granted by default. The
| framework default would allow any origin ("*"). A separately hosted client
| can be allowed explicitly with a comma separated CORS_ALLOWED_ORIGINS.
|
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST'],

    'allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],

    'exposed_headers' => ['Location'],

    'max_age' => 600,

    // Bearer tokens only: cookies are never sent cross-origin.
    'supports_credentials' => false,

];
