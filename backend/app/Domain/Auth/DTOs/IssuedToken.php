<?php

declare(strict_types=1);

namespace App\Domain\Auth\DTOs;

use App\Models\User;
use Carbon\CarbonImmutable;

final readonly class IssuedToken
{
    public function __construct(
        public string $plainTextToken,
        public CarbonImmutable $expiresAt,
        public User $user,
    ) {}
}
