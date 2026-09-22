<?php

declare(strict_types=1);

namespace App\Domain\Imports\Contracts;

/**
 * Schedules the background processing of an import.
 */
interface ImportProcessingQueue
{
    public function push(int $importId): void;
}
