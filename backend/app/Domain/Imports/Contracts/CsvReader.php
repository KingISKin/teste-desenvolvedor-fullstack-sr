<?php

declare(strict_types=1);

namespace App\Domain\Imports\Contracts;

interface CsvReader
{
    /**
     * Lazily yields CSV records keyed by their 1-based line number, without
     * loading the whole stream into memory. Blank lines are skipped.
     *
     * @param  resource  $stream
     * @return iterable<int, list<string|null>>
     */
    public function records($stream): iterable;
}
