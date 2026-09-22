<?php

declare(strict_types=1);

return [

    // Private disk (never publicly served) where uploaded CSV files are kept
    // until the background job has processed them.
    'disk' => env('IMPORTS_DISK', 'local'),

    'directory' => 'imports',

    // Upload size limit in kilobytes (nginx client_max_body_size must allow it).
    'max_upload_kb' => (int) env('IMPORTS_MAX_UPLOAD_KB', 20480),

    // Rows per bulk INSERT; each chunk and its progress checkpoint are
    // committed in a single database transaction.
    'chunk_size' => (int) env('IMPORTS_CHUNK_SIZE', 1000),

    // Maximum number of row errors persisted per import (keeps the row small).
    'max_stored_errors' => (int) env('IMPORTS_MAX_STORED_ERRORS', 100),

];
