<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\Contracts\UserRepository;
use App\Models\User;

final class EloquentUserRepository implements UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }
}
