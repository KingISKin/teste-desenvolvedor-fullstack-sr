<?php

declare(strict_types=1);

namespace App\Domain\Imports\DTOs;

/**
 * An import problem tied to a CSV line. Line 0 means a file-level problem.
 */
final readonly class RowError
{
    public function __construct(
        public int $line,
        public string $message,
    ) {}

    /**
     * @return array{line: int, message: string}
     */
    public function toArray(): array
    {
        return ['line' => $this->line, 'message' => $this->message];
    }
}
