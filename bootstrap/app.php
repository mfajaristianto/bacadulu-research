<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureEmailVerifiedWhenRequired;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\ResearchLiveRefresh;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SentryRequestContext;
use App\Http\Middleware\SourceProcessingUi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(
    basePath:
        dirname(
            __DIR__
        )
)
    ->withRouting(

        web:
            __DIR__
            .'/../routes/web.php',

        commands:
            __DIR__
            .'/../routes/console.php',

        health:
            '/up',

        then:
            function (): void {

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/google-auth.php'
                        )
                    );

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/documents.php'
                        )
                    );

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/admin-monitoring.php'
                        )
                    );

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/admin-console.php'
                        )
                    );

                /*
                 * Queue-aware Research Routes.
                 *
                 * Harus tetap terakhir.
                 */

                Route::middleware(
                    'web'
                )
                    ->group(
                        base_path(
                            'routes/research-queue.php'
                        )
                    );
            }
    )
    ->withMiddleware(
        function (
            Middleware $middleware
        ): void {

            /*
            |--------------------------------------------------------------------------
            | Trust Reverse Proxy / Ngrok
            |--------------------------------------------------------------------------
            |
            | Browser mengakses aplikasi melalui HTTPS ngrok:
            |
            | https://xxxxx.ngrok-free.dev
            |
            | tetapi ngrok meneruskan request ke Laravel melalui HTTP localhost.
            |
            | Dengan trustProxies(), Laravel akan mempercayai header:
            |
            | X-Forwarded-Proto: https
            |
            | sehingga route(), url(), form action, session, dan pushState
            | menghasilkan URL HTTPS, bukan HTTP.
            |
            */

            $middleware->trustProxies(
                at: '*'
            );

            /*
            |--------------------------------------------------------------------------
            | Middleware Aliases
            |--------------------------------------------------------------------------
            */

            $middleware
                ->alias([

                    'active' =>
                        EnsureActiveUser::class,

                    'admin' =>
                        EnsureAdmin::class,

                    'verified.if.required' =>
                        EnsureEmailVerifiedWhenRequired::class,

                    'profile.complete' =>
                        EnsureProfileComplete::class,

                    'research.live-refresh' =>
                        ResearchLiveRefresh::class,

                ]);

            /*
            |--------------------------------------------------------------------------
            | Web Middleware
            |--------------------------------------------------------------------------
            */

            $middleware
                ->web(
                    append: [

                        /*
                         * Tambahkan safe user/route context.
                         */
                        SentryRequestContext::class,

                        SecurityHeaders::class,

                        SourceProcessingUi::class,

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
            | Sentry
            |--------------------------------------------------------------------------
            */

            Integration::handles(
                $exceptions
            );

            /*
            |--------------------------------------------------------------------------
            | Sensitive Laravel Flash Data
            |--------------------------------------------------------------------------
            */

            $exceptions
                ->dontFlash([
                    'current_password',
                    'password',
                    'password_confirmation',
                ]);

            /*
            |--------------------------------------------------------------------------
            | Rate Limit
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
                                        'message' =>
                                            'Terlalu banyak permintaan. Silakan coba lagi nanti.',
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