<?php

declare(strict_types=1);

namespace App\Domain\Imports\DTOs;

/**
 * Framework-agnostic view of an uploaded file: where its bytes currently
 * live (temporary path) and the name the client gave it (display only).
 */
final readonly class UploadedCsv
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
    ) {}
}
