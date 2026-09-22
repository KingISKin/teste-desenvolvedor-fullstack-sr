<?php

declare(strict_types=1);

namespace App\Domain\Imports\Exceptions;

use DomainException;

final class InvalidTransactionRow extends DomainException
{
    /**
     * @param  non-empty-list<string>  $violations
     */
    public static function withViolations(array $violations): self
    {
        return new self(implode(' ', $violations));
    }
}
