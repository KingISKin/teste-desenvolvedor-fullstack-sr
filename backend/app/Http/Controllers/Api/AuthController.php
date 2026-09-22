<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Actions\IssueAccessToken;
use App\Domain\Auth\Actions\RevokeCurrentAccessToken;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\AccessTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final class AuthController
{
    public function login(LoginRequest $request, IssueAccessToken $issueAccessToken): AccessTokenResource
    {
        return new AccessTokenResource($issueAccessToken->handle($request->toCredentials()));
    }

    public function logout(#[CurrentUser] User $user, RevokeCurrentAccessToken $revokeToken): Response
    {
        $revokeToken->handle($user);

        return response()->noContent();
    }

    public function me(#[CurrentUser] User $user): UserResource
    {
        return new UserResource($user);
    }
}
