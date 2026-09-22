<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\TransactionImport;
use App\Policies\TransactionImportPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Surface lazy loading / silently discarded attributes during development and tests.
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::policy(TransactionImport::class, TransactionImportPolicy::class);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Brute-force protection: per e-mail + IP pair.
        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));

        // Runs before auth:sanctum, so the token is resolved explicitly (the guard
        // caches the user, the auth middleware does not query it again).
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(120)->by(
            $request->user('sanctum')?->getAuthIdentifier() ?? $request->ip(),
        ));
    }
}
