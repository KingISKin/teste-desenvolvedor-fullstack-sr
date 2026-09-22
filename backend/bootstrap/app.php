<?php

declare(strict_types=1);

use App\Domain\Auth\Exceptions\InvalidCredentials;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stateless token API: no cookies, sessions or CSRF are involved.
        $middleware->throttleApi('api');

        // There is no login page to redirect to; guests always get a JSON 401.
        $middleware->redirectGuestsTo(static fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API never answers with HTML or redirects, even without an Accept header.
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // Keep the domain free of HTTP concerns: translate to a standard 422 payload here.
        $exceptions->map(
            InvalidCredentials::class,
            static fn (): ValidationException => ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]),
        );
    })->create();
