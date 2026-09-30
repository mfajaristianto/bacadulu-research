<?php

use App\Http\Controllers\Admin\AiRunController;
use Illuminate\Support\Facades\Route;

$adminPath =
    config(
        'research.admin_path'
    );

/*
|--------------------------------------------------------------------------
| Admin Monitoring
|--------------------------------------------------------------------------
|
| Read-only operational/audit screens.
|
| Tidak ada route update/delete terhadap AI Run karena:
|
| AI Run
| → retrieval
| → evidence
| → citation
|
| merupakan provenance/audit trail.
|
*/

Route::prefix(
    $adminPath
)
    ->name(
        'admin.'
    )
    ->middleware(
        'admin'
    )
    ->group(
        function (): void {
            Route::get(
                '/ai-runs',
                [
                    AiRunController::class,
                    'index',
                ]
            )->name(
                'ai-runs.index'
            );

            Route::get(
                '/ai-runs/{aiRun}',
                [
                    AiRunController::class,
                    'show',
                ]
            )->name(
                'ai-runs.show'
            );
        }
    );