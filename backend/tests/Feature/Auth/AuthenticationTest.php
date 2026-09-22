<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'correct-password',
    ]);
});

it('issues an expiring bearer token for valid credentials', function (): void {
    Carbon::setTestNow('2026-09-22 12:00:00');

    $response = $this->postJson('/api/auth/login', [
        'email' => 'Jane@Example.com',
        'password' => 'correct-password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $this->user->id)
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonMissingPath('user.password');

    $token = PersonalAccessToken::findToken($response->json('token'));

    expect($token)->not->toBeNull()
        ->and($token->tokenable_id)->toBe($this->user->id)
        ->and($token->expires_at->toDateTimeString())
        ->toBe(Carbon::now()->addMinutes(config('sanctum.expiration'))->toDateTimeString());
});

it('rejects invalid credentials with a generic 422 error', function (string $email, string $password): void {
    $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
})->with([
    'wrong password' => ['jane@example.com', 'wrong-password'],
    'unknown e-mail' => ['nobody@example.com', 'correct-password'],
]);

it('validates the login payload', function (): void {
    $this->postJson('/api/auth/login', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('rate limits repeated login attempts', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'correct-password'])
        ->assertTooManyRequests();
});

it('returns the authenticated user', function (): void {
    $token = $this->user->createToken('api')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => 'jane@example.com',
        ]]);
});

it('revokes only the current token on logout', function (): void {
    $current = $this->user->createToken('api')->plainTextToken;
    $other = $this->user->createToken('api')->plainTextToken;

    $this->withToken($current)->postJson('/api/auth/logout')->assertNoContent();

    $this->forgetAuthenticatedUser();
    $this->withToken($current)->getJson('/api/auth/me')->assertUnauthorized();

    $this->forgetAuthenticatedUser();
    $this->withToken($other)->getJson('/api/auth/me')->assertOk();
});

it('rejects expired tokens', function (): void {
    $token = $this->user->createToken('api', ['*'], Carbon::now()->addMinutes(5))->plainTextToken;

    Carbon::setTestNow(Carbon::now()->addMinutes(6));

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('rejects tokens older than the configured expiration even without expires_at', function (): void {
    $token = $this->user->createToken('api')->plainTextToken;

    Carbon::setTestNow(Carbon::now()->addMinutes(config('sanctum.expiration') + 1));

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('protects every private endpoint with a JSON 401', function (string $method, string $uri): void {
    $this->json($method, $uri)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
})->with([
    ['POST', '/api/auth/logout'],
    ['GET', '/api/auth/me'],
    ['GET', '/api/dashboard'],
    ['GET', '/api/transactions'],
    ['POST', '/api/imports'],
    ['GET', '/api/imports/1'],
]);

it('answers JSON 401 even when the client does not ask for JSON', function (): void {
    $this->get('/api/dashboard')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json');
});
