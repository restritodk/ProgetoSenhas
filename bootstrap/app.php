<?php

use App\Http\Middleware\EnsureOperationalContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            RateLimiter::for('login', function (Request $request): Limit {
                $email = Str::lower((string) $request->input('email'));

                return Limit::perMinute(5)->by($email.'|'.$request->ip());
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'operational' => EnsureOperationalContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();
