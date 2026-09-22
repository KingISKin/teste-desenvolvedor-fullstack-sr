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
final class IssueAccessToken
{
    private const TOKEN_NAME = 'api';

    /** Hash checked when the e-mail is unknown; created once per worker process. */
    private static ?string $dummyHash = null;

    public function __construct(
        private readonly UserRepository $users,
        private readonly Hasher $hasher,
        private readonly Config $config,
    ) {}

    /**
     * @throws InvalidCredentials
     */
    public function handle(LoginCredentials $credentials): IssuedToken
    {
        $user = $this->users->findByEmail($credentials->email);

        // Always pay for one hash verification so response times do not reveal
        // whether an e-mail address is registered (user enumeration).
        $hash = $user?->password ?? (self::$dummyHash ??= $this->hasher->make(bin2hex(random_bytes(16))));
        $passwordMatches = $this->hasher->check($credentials->password, $hash);

        if ($user === null || ! $passwordMatches) {
            throw new InvalidCredentials;
        }

        $expiresAt = Carbon::now()->addMinutes((int) $this->config->get('sanctum.expiration'));
        $token = $user->createToken(self::TOKEN_NAME, ['*'], $expiresAt);

        return new IssuedToken($token->plainTextToken, $expiresAt->toImmutable(), $user);
    }
}
