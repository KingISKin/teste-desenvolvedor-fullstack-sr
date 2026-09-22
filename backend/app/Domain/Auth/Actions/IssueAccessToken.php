<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Contracts\UserRepository;
use App\Domain\Auth\DTOs\IssuedToken;
use App\Domain\Auth\DTOs\LoginCredentials;
use App\Domain\Auth\Exceptions\InvalidCredentials;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Carbon;

/**
 * Verifies credentials and issues an expiring Sanctum personal access token.
 */
final readonly class IssueAccessToken
{
    private const TOKEN_NAME = 'api';

    public function __construct(
        private UserRepository $users,
        private Hasher $hasher,
        private Config $config,
    ) {}

    /**
     * @throws InvalidCredentials
     */
    public function handle(LoginCredentials $credentials): IssuedToken
    {
        $user = $this->users->findByEmail($credentials->email);

        if ($user === null || ! $this->hasher->check($credentials->password, $user->password)) {
            throw new InvalidCredentials;
        }

        $expiresAt = Carbon::now()->addMinutes((int) $this->config->get('sanctum.expiration'));
        $token = $user->createToken(self::TOKEN_NAME, ['*'], $expiresAt);

        return new IssuedToken($token->plainTextToken, $expiresAt->toImmutable(), $user);
    }
}
