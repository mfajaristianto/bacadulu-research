<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureEmailVerifiedWhenRequired;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',

        commands:
            __DIR__
            .'/../routes/console.php',

        health:
            '/up',

        then:
            function (): void {

                /*
                |--------------------------------------------------------------------------
                | Google Auth Linking
                |--------------------------------------------------------------------------
                */

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/google-auth.php'
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | Research Document Engine
                |--------------------------------------------------------------------------
                */

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/documents.php'
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | Admin Monitoring
                |--------------------------------------------------------------------------
                */

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/admin-monitoring.php'
                        )
                    );
            }
    )
    ->withMiddleware(
        function (
            Middleware $middleware
        ): void {
            $middleware
                ->alias([
                    'active'
                        => EnsureActiveUser::class,

                    'admin'
                        => EnsureAdmin::class,

                    'verified.if.required'
                        => EnsureEmailVerifiedWhenRequired::class,

                    'profile.complete'
                        => EnsureProfileComplete::class,
                ]);


            $middleware
                ->web(
                    append: [
                        SecurityHeaders::class,
                    ]
                );
        }
    )
    ->withExceptions(
        function (
            Exceptions $exceptions
        ): void {

            /*
            |--------------------------------------------------------------------------
            | Sensitive request fields
            |--------------------------------------------------------------------------
            |
            | Password jangan ikut ditampilkan di halaman exception/debug.
            |
            */

            $exceptions
                ->dontFlash([
                    'current_password',
                    'password',
                    'password_confirmation',
                ]);


            /*
            |--------------------------------------------------------------------------
            | Rate Limit Response
            |--------------------------------------------------------------------------
            */

            $exceptions
                ->render(
                    function (
                        TooManyRequestsHttpException $exception,
                        $request
                    ) {
                        if (
                            $request
                                ->expectsJson()
                        ) {
                            return response()
                                ->json(
                                    [
                                        'message'
                                            => 'Terlalu banyak permintaan. Silakan coba lagi nanti.',
                                    ],
                                    429
                                );
                        }


                        return response()
                            ->view(
                                'errors.429',
                                [],
                                429
                            );
                    }
                );
        }
    )
    ->create();