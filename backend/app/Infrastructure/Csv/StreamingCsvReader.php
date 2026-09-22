<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

use App\Domain\Imports\Contracts\CsvReader;

/**
 * fgetcsv-based reader: memory usage stays constant regardless of file size.
 */
final class StreamingCsvReader implements CsvReader
{
    public function records($stream): iterable
    {
        $line = 0;

        // RFC 4180: no backslash escape character, quotes are doubled instead.
        while (($fields = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $line++;

            // fgetcsv returns [null] for an empty line.
            if ($fields === [null]) {
                continue;
            }

            yield $line => $fields;
        }
    }
}
