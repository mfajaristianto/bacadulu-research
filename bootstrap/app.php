<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureEmailVerifiedWhenRequired;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'admin' => EnsureAdmin::class,
            'verified.if.required' => EnsureEmailVerifiedWhenRequired::class,
            'profile.complete' => EnsureProfileComplete::class,
        ]);
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, $request) {
            if ($request->expectsJson()) return response()->json(['message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'], 429);
            return response()->view('errors.429', [], 429);
        });
    })->create();
