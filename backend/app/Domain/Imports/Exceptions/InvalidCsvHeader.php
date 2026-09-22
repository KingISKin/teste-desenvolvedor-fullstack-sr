<?php

declare(strict_types=1);

namespace App\Domain\Imports\Exceptions;

use DomainException;

final class InvalidCsvHeader extends DomainException
{
    /**
     * @param  list<string>  $expected
     */
    public static function expected(array $expected): self
    {
        return new self(sprintf('Invalid header. Expected exactly: %s.', implode(',', $expected)));
    }
}
