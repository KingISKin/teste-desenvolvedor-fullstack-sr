<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

use App\Domain\Imports\Contracts\CsvReader;

/**
 * Reads one physical line at a time (fgets + str_getcsv), so memory stays
 * constant regardless of file size and every record is keyed by its exact
 * physical line number, which is what users see in their editor.
 *
 * Trade-off: quoted fields spanning several lines are not supported. The
 * import format (date, description, amount, type) never contains line
 * breaks; such a field would surface as invalid rows reported on the right
 * lines instead of silently shifting every following line number.
 */
final class StreamingCsvReader implements CsvReader
{
    public function records($stream): iterable
    {
        $line = 0;

        while (($raw = fgets($stream)) !== false) {
            $line++;
            $raw = rtrim($raw, "\r\n");

            if ($raw === '') {
                continue;
            }

            // RFC 4180: no backslash escape character, quotes are doubled instead.
            yield $line => str_getcsv($raw, ',', '"', '');
        }
    }
}
