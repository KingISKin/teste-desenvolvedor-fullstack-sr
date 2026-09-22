<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Logs out the current device by deleting only the token used for the request.
 */
final readonly class RevokeCurrentAccessToken
{
    public function handle(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
