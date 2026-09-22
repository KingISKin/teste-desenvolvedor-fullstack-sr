<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Demo user
|--------------------------------------------------------------------------
|
| There is no public registration. A single demo account is seeded from the
| environment so credentials never live in source control.
|
*/

return [
    'user' => [
        'name' => env('DEMO_USER_NAME', 'Demo User'),
        'email' => env('DEMO_USER_EMAIL'),
        'password' => env('DEMO_USER_PASSWORD'),
    ],
];
