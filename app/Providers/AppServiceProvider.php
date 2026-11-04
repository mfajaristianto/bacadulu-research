<?php

namespace App\Providers;

use App\Services\Monitoring\SentryContext;
use App\Services\Research\ResearchSettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(
        ResearchSettingsService $researchSettings,
        SentryContext $sentryContext
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Runtime Research Settings
        |--------------------------------------------------------------------------
        */

        $researchSettings
            ->applyOverrides();

        /*
        |--------------------------------------------------------------------------
        | Sentry Queue Context
        |--------------------------------------------------------------------------
        |
        | queue:work adalah long-running process.
        |
        | Sebelum job:
        | reset context lama + pasang context job sekarang.
        |
        | Setelah job sukses:
        | bersihkan context.
        |
        | Kalau job gagal, jangan dibersihkan di exception event dulu,
        | supaya SDK masih dapat mengambil context saat exception dicapture.
        |
        | Job berikutnya tetap aman karena Queue::before akan reset lagi.
        |
        */

        Queue::before(
            function (
                JobProcessing $event
            ) use (
                $sentryContext
            ): void {
                $sentryContext
                    ->forQueueJob(
                        $event
                            ->job
                    );
            }
        );

        Queue::after(
            function (
                JobProcessed $event
            ) use (
                $sentryContext
            ): void {
                $sentryContext
                    ->clear();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Vite
        |--------------------------------------------------------------------------
        */

        Vite::useHotFile(
            storage_path(
                'framework/vite.hot'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Asset Origin
        |--------------------------------------------------------------------------
        |
        | ASSET_URL membuat asset() dan @vite selalu mengarah ke satu host. Jika host
        | itu berbeda dari host request yang sedang berjalan (mis. ASSET_URL masih
        | domain ngrok lama padahal dibuka dari 127.0.0.1 atau domain produksi),
        | CSS/JS/gambar/font diblokir CSP 'self'. Dalam kasus itu ASSET_URL diabaikan
        | dan aset mengikuti host request aktif (tanpa hardcode host apa pun).
        |
        */

        if (! $this->app->runningInConsole()) {
            $assetHost = parse_url((string) config('app.asset_url'), PHP_URL_HOST);

            if ($assetHost && strcasecmp($assetHost, request()->getHost()) !== 0) {
                URL::useAssetOrigin(null);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Rate Limiters
        |--------------------------------------------------------------------------
        */

        RateLimiter::for(
            'login',
            fn (
                Request $request
            ) => [

                Limit::perMinute(
                    8
                )
                    ->by(
                        strtolower(
                            (string)
                                $request
                                    ->input(
                                        'email'
                                    )
                        )
                        .'|'
                        .$request
                            ->ip()
                    ),

                Limit::perMinute(
                    20
                )
                    ->by(
                        $request
                            ->ip()
                    ),

            ]
        );

        RateLimiter::for(
            'register',
            fn (
                Request $request
            ) => [

                Limit::perMinute(
                    5
                )
                    ->by(
                        $request
                            ->ip()
                    ),

                Limit::perHour(
                    20
                )
                    ->by(
                        $request
                            ->ip()
                    ),

            ]
        );

        RateLimiter::for(
            'admin-login',
            fn (
                Request $request
            ) =>
                Limit::perMinute(
                    5
                )
                    ->by(
                        $request
                            ->ip()
                    )
        );

        RateLimiter::for(
            'research',
            fn (
                Request $request
            ) =>
                Limit::perMinute(
                    12
                )
                    ->by(
                        $request
                            ->user()
                            ?->id
                        ?:
                        $request
                            ->ip()
                    )
        );

        RateLimiter::for(
            'profile',
            fn (
                Request $request
            ) =>
                Limit::perMinute(
                    10
                )
                    ->by(
                        $request
                            ->user()
                            ?->id
                        ?:
                        $request
                            ->ip()
                    )
        );

        RateLimiter::for(
            'library',
            fn (
                Request $request
            ) =>
                Limit::perMinute(
                    60
                )
                    ->by(
                        $request
                            ->user()
                            ?->id
                        ?:
                        $request
                            ->ip()
                    )
        );
    }
}