<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Queue connections
    |--------------------------------------------------------------------------
    |
    | Redis runs the queue in every real environment; "sync" is only used by
    | the test suite. No other driver is configured on purpose.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            // Must exceed the longest job timeout (import job: 600s), otherwise a
            // still-running job would be handed to a second worker.
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 660),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed jobs
    |--------------------------------------------------------------------------
    |
    | Permanently failed jobs are kept in the database for inspection and
    | `php artisan queue:retry`.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
