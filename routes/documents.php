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
    | Documents
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/documents',
        [
            DocumentGenerationController::class,
            'index',
        ]
    )->name(
        'documents.index'
    );


    Route::get(
        '/documents/create',
        [
            DocumentGenerationController::class,
            'create',
        ]
    )->name(
        'documents.create'
    );


    /*
     * Membuat draft awal.
     *
     * BELUM render PDF/DOCX.
     */

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


    /*
     * Simpan edit manual.
     */

    Route::patch(
        '/documents/{documentGeneration}',
        [
            DocumentGenerationController::class,
            'update',
        ]
    )
        ->middleware(
            'throttle:30,1'
        )
        ->name(
            'documents.update'
        );


    /*
     * Cetak final.
     *
     * Baru endpoint ini membuat
     * PDF atau DOCX.
     */

    Route::post(
        '/documents/{documentGeneration}/render',
        [
            DocumentGenerationController::class,
            'render',
        ]
    )
        ->middleware(
            'throttle:10,1'
        )
        ->name(
            'documents.render'
        );


    /*
     * PDF inline browser preview.
     */

    Route::get(
        '/documents/{documentGeneration}/preview',
        [
            DocumentGenerationController::class,
            'preview',
        ]
    )
        ->middleware(
            'throttle:60,1'
        )
        ->name(
            'documents.preview'
        );


    Route::delete(
        '/documents/{documentGeneration}',
        [
            DocumentGenerationController::class,
            'destroy',
        ]
    )
        ->middleware(
            'throttle:15,1'
        )
        ->name(
            'documents.destroy'
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