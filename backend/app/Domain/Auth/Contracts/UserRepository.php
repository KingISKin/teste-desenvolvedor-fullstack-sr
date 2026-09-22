<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

use App\Models\User;

interface UserRepository
{
    public function findByEmail(string $email): ?User;
}
