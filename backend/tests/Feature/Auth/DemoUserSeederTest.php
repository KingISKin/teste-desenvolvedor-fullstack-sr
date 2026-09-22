<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;

it('seeds the demo user from the environment and lets it log in', function (): void {
    config(['demo.user.email' => 'Demo@Example.com', 'demo.user.password' => 'demo-password']);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'demo@example.com')->count())->toBe(1);

    $this->postJson('/api/auth/login', ['email' => 'demo@example.com', 'password' => 'demo-password'])
        ->assertOk();
});

it('is idempotent and re-syncs the password on every run', function (): void {
    config(['demo.user.email' => 'demo@example.com', 'demo.user.password' => 'first']);
    $this->seed(DemoUserSeeder::class);

    config(['demo.user.password' => 'second']);
    $this->seed(DemoUserSeeder::class);

    expect(User::query()->count())->toBe(1);

    $this->postJson('/api/auth/login', ['email' => 'demo@example.com', 'password' => 'second'])
        ->assertOk();
});

it('skips seeding when credentials are not configured', function (): void {
    config(['demo.user.email' => null, 'demo.user.password' => null]);

    $this->seed(DemoUserSeeder::class);

    expect(User::query()->count())->toBe(0);
});
