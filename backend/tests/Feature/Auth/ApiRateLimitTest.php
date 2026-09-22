<?php

declare(strict_types=1);

use App\Models\User;

it('throttles authenticated API traffic per user, not per shared IP', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceToken = $alice->createToken('api')->plainTextToken;
    $bobToken = $bob->createToken('api')->plainTextToken;

    foreach (range(1, 120) as $request) {
        $this->forgetAuthenticatedUser();
        $this->withToken($aliceToken)->getJson('/api/auth/me')->assertOk();
    }

    $this->forgetAuthenticatedUser();
    $this->withToken($aliceToken)->getJson('/api/auth/me')->assertTooManyRequests();

    $this->forgetAuthenticatedUser();
    $this->withToken($bobToken)->getJson('/api/auth/me')->assertOk();
});
