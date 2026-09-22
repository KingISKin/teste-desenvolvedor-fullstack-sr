<?php

declare(strict_types=1);

namespace App\Domain\Auth\DTOs;

use SensitiveParameter;

final readonly class LoginCredentials
{
    public function __construct(
        public string $email,
        #[SensitiveParameter]
        public string $password,
    ) {}
}
