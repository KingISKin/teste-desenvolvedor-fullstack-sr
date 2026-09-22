<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use DomainException;

/**
 * Deliberately generic: never reveals whether the e-mail or the password was wrong.
 */
final class InvalidCredentials extends DomainException
{
    public function __construct()
    {
        parent::__construct('The provided credentials are incorrect.');
    }
}
