<?php

use App\Http\Controllers\ResearchController;
use App\Http\Controllers\ResearchRunStatusController;
use App\Http\Controllers\ResearchSubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Queue-aware Research Routes
|--------------------------------------------------------------------------
|
| File ini dimuat PALING AKHIR.
|
| Jadi route synchronous lama:
|
| POST /research/run
|
| akan menggunakan ResearchSubmissionController.
|
| Kita sengaja tidak menghapus controller lama dulu
| supaya rollback tetap gampang.
|
*/

Route::middleware([
    'auth',
    'active',
    'verified.if.required',
    'profile.complete',
])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Workspace
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/workspace',
        [
            ResearchController::class,
            'workspace',
        ]
    )
        ->name(
            'workspace'
        );

    Route::get(
        '/workspace/{conversation}',
        [
            ResearchController::class,
            'workspace',
        ]
    )
        ->name(
            'workspace.conversation'
        );

    /*
    |--------------------------------------------------------------------------
    | Async Research Submission
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/research/run',
        [
            ResearchSubmissionController::class,
            'store',
        ]
    )
        ->middleware(
            'throttle:research'
        )
        ->name(
            'research.run'
        );

    /*
    |--------------------------------------------------------------------------
    | Live Status
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/research/runs/{aiRun}/status',
        [
            ResearchRunStatusController::class,
            'show',
        ]
    )
        ->middleware(
            'throttle:120,1'
        )
        ->name(
            'research.runs.status'
        );
});