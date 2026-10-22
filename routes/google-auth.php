<?php

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;

// Pause Google account-linking endpoints without deleting any OAuth code.
if (! config('research.google_auth_enabled', false)) {
    return;
}

/*
|--------------------------------------------------------------------------
| Google Account Linking
|--------------------------------------------------------------------------
|
| Dipisahkan dari routes/web.php supaya auth linking
| lebih mudah diaudit dan tidak bercampur dengan
| route utama aplikasi.
|
*/

Route::middleware(
    'guest'
)
    ->group(
        function (): void {
            Route::get(
                '/auth/google/link',
                [
                    GoogleController::class,
                    'linkForm',
                ]
            )
                ->name(
                    'google.link.form'
                );

            Route::post(
                '/auth/google/link',
                [
                    GoogleController::class,
                    'link',
                ]
            )
                ->middleware(
                    'throttle:6,1'
                )
                ->name(
                    'google.link.store'
                );
        }
    );
