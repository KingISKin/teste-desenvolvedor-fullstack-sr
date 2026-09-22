<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Idempotent: creates the demo account or re-syncs it with the environment.
 */
final class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('demo.user.email');
        $password = (string) config('demo.user.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('DEMO_USER_EMAIL / DEMO_USER_PASSWORD not set: demo user not seeded.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => mb_strtolower($email)],
            ['name' => (string) config('demo.user.name'), 'password' => $password],
        );
    }
}
