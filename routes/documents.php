<?php

use App\Http\Controllers\DocumentGenerationController;
use App\Http\Controllers\ResearchOutputPresentationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Research Output + Document Engine
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'active',
    'verified.if.required',
    'profile.complete',
])->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Research Output Presentation
    |--------------------------------------------------------------------------
    |
    | Endpoint ini hanya mengirim presentation metadata
    | milik user sendiri.
    |
    */

    Route::get(
        '/research/runs/{aiRun}/presentation',
        [
            ResearchOutputPresentationController::class,
            'run',
        ]
    )
        ->middleware(
            'throttle:60,1'
        )
        ->name(
            'research.runs.presentation'
        );

    Route::get(
        '/saved/{savedResult}/presentation',
        [
            ResearchOutputPresentationController::class,
            'saved',
        ]
    )
        ->middleware(
            'throttle:60,1'
        )
        ->name(
            'saved.presentation'
        );

    /*
    |--------------------------------------------------------------------------
    | Document Engine
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/documents/create',
        [
            DocumentGenerationController::class,
            'create',
        ]
    )->name(
        'documents.create'
    );

    Route::post(
        '/documents',
        [
            DocumentGenerationController::class,
            'store',
        ]
    )
        ->middleware(
            'throttle:10,1'
        )
        ->name(
            'documents.store'
        );

    Route::get(
        '/documents/{documentGeneration}',
        [
            DocumentGenerationController::class,
            'show',
        ]
    )->name(
        'documents.show'
    );

    Route::patch(
        '/documents/{documentGeneration}',
        [
            DocumentGenerationController::class,
            'update',
        ]
    )
        ->middleware(
            'throttle:15,1'
        )
        ->name(
            'documents.update'
        );

    Route::get(
        '/documents/{documentGeneration}/download/{format}',
        [
            DocumentGenerationController::class,
            'download',
        ]
    )
        ->whereIn(
            'format',
            [
                'docx',
                'pdf',
            ]
        )
        ->middleware(
            'throttle:30,1'
        )
        ->name(
            'documents.download'
        );
});