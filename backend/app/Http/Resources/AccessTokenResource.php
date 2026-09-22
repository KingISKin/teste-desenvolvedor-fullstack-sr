<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Auth\DTOs\IssuedToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property IssuedToken $resource
 */
final class AccessTokenResource extends JsonResource
{
    /**
     * Login payload is returned unwrapped: { token, token_type, expires_at, user }.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
            'user' => new UserResource($this->resource->user),
        ];
    }
}
