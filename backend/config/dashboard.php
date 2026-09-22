<?php

declare(strict_types=1);

return [

    // Cached summaries are invalidated by events; the TTL is only a safety net.
    'cache_ttl_seconds' => (int) env('DASHBOARD_CACHE_TTL', 3600),

];
