<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\GlossaryController;
use App\Http\Controllers\Admin\PromptVersionController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SourceIngestionStatusController;
use App\Http\Controllers\Admin\SourceSyncController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

$adminPath =
    config(
        'research.admin_path'
    );

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

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/categories',
                [
                    CategoryController::class,
                    'index',
                ]
            )
                ->name(
                    'categories.index'
                );

            Route::get(
                '/categories/create',
                [
                    CategoryController::class,
                    'create',
                ]
            )
                ->name(
                    'categories.create'
                );

            Route::post(
                '/categories',
                [
                    CategoryController::class,
                    'store',
                ]
            )
                ->name(
                    'categories.store'
                );

            Route::get(
                '/categories/{category}/edit',
                [
                    CategoryController::class,
                    'edit',
                ]
            )
                ->name(
                    'categories.edit'
                );

            Route::put(
                '/categories/{category}',
                [
                    CategoryController::class,
                    'update',
                ]
            )
                ->name(
                    'categories.update'
                );

            /*
            |--------------------------------------------------------------------------
            | Collections
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/collections',
                [
                    CollectionController::class,
                    'index',
                ]
            )
                ->name(
                    'collections.index'
                );

            Route::get(
                '/collections/create',
                [
                    CollectionController::class,
                    'create',
                ]
            )
                ->name(
                    'collections.create'
                );

            Route::post(
                '/collections',
                [
                    CollectionController::class,
                    'store',
                ]
            )
                ->name(
                    'collections.store'
                );

            Route::get(
                '/collections/{collection}/edit',
                [
                    CollectionController::class,
                    'edit',
                ]
            )
                ->name(
                    'collections.edit'
                );

            Route::put(
                '/collections/{collection}',
                [
                    CollectionController::class,
                    'update',
                ]
            )
                ->name(
                    'collections.update'
                );

            /*
            |--------------------------------------------------------------------------
            | Source Sync
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/source-sync',
                [
                    SourceSyncController::class,
                    'index',
                ]
            )
                ->name(
                    'source-sync.index'
                );

            Route::post(
                '/source-sync/scan',
                [
                    SourceSyncController::class,
                    'scan',
                ]
            )
                ->name(
                    'source-sync.scan'
                );

            Route::post(
                '/source-sync/items/{syncItem}/import',
                [
                    SourceSyncController::class,
                    'import',
                ]
            )
                ->name(
                    'source-sync.import'
                );

            Route::patch(
                '/source-sync/items/{syncItem}/ignore',
                [
                    SourceSyncController::class,
                    'ignore',
                ]
            )
                ->name(
                    'source-sync.ignore'
                );

            /*
            |--------------------------------------------------------------------------
            | Library Ingestion Status
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/library/{document}/ingestion-status',
                [
                    SourceIngestionStatusController::class,
                    'show',
                ]
            )
                ->name(
                    'library.ingestion-status'
                );

            /*
            |--------------------------------------------------------------------------
            | Glossary
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/glossary',
                [
                    GlossaryController::class,
                    'index',
                ]
            )
                ->name(
                    'glossary.index'
                );

            Route::get(
                '/glossary/create',
                [
                    GlossaryController::class,
                    'create',
                ]
            )
                ->name(
                    'glossary.create'
                );

            Route::post(
                '/glossary',
                [
                    GlossaryController::class,
                    'store',
                ]
            )
                ->name(
                    'glossary.store'
                );

            Route::get(
                '/glossary/{glossaryTerm}/edit',
                [
                    GlossaryController::class,
                    'edit',
                ]
            )
                ->name(
                    'glossary.edit'
                );

            Route::put(
                '/glossary/{glossaryTerm}',
                [
                    GlossaryController::class,
                    'update',
                ]
            )
                ->name(
                    'glossary.update'
                );

            Route::delete(
                '/glossary/{glossaryTerm}',
                [
                    GlossaryController::class,
                    'destroy',
                ]
            )
                ->name(
                    'glossary.destroy'
                );

            /*
            |--------------------------------------------------------------------------
            | Document Templates
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/document-templates',
                [
                    DocumentTemplateController::class,
                    'index',
                ]
            )
                ->name(
                    'document-templates.index'
                );

            Route::get(
                '/document-templates/create',
                [
                    DocumentTemplateController::class,
                    'create',
                ]
            )
                ->name(
                    'document-templates.create'
                );

            Route::post(
                '/document-templates',
                [
                    DocumentTemplateController::class,
                    'store',
                ]
            )
                ->name(
                    'document-templates.store'
                );

            Route::get(
                '/document-templates/{documentTemplate}/edit',
                [
                    DocumentTemplateController::class,
                    'edit',
                ]
            )
                ->name(
                    'document-templates.edit'
                );

            Route::put(
                '/document-templates/{documentTemplate}',
                [
                    DocumentTemplateController::class,
                    'update',
                ]
            )
                ->name(
                    'document-templates.update'
                );

            Route::delete(
                '/document-templates/{documentTemplate}',
                [
                    DocumentTemplateController::class,
                    'destroy',
                ]
            )
                ->name(
                    'document-templates.destroy'
                );

            /*
            |--------------------------------------------------------------------------
            | Prompt Versions
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/prompt-versions',
                [
                    PromptVersionController::class,
                    'index',
                ]
            )
                ->name(
                    'prompt-versions.index'
                );

            Route::post(
                '/prompt-versions/{prompt}/duplicate',
                [
                    PromptVersionController::class,
                    'duplicate',
                ]
            )
                ->name(
                    'prompt-versions.duplicate'
                );

            Route::patch(
                '/prompt-versions/{prompt}/activate',
                [
                    PromptVersionController::class,
                    'activate',
                ]
            )
                ->name(
                    'prompt-versions.activate'
                );

            /*
            |--------------------------------------------------------------------------
            | Users
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/users',
                [
                    UserController::class,
                    'index',
                ]
            )
                ->name(
                    'users.index'
                );

            Route::get(
                '/users/{user}',
                [
                    UserController::class,
                    'show',
                ]
            )
                ->name(
                    'users.show'
                );

            Route::patch(
                '/users/{user}/status',
                [
                    UserController::class,
                    'status',
                ]
            )
                ->name(
                    'users.status'
                );

            /*
            |--------------------------------------------------------------------------
            | System
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/audit',
                [
                    AuditLogController::class,
                    'index',
                ]
            )
                ->name(
                    'audit.index'
                );

            Route::get(
                '/security',
                [
                    SecurityController::class,
                    'index',
                ]
            )
                ->name(
                    'security.index'
                );
         Route::get(
    '/settings',
    [
        SettingsController::class,
        'index',
    ]
)
    ->name(
        'settings.index'
    );

Route::put(
    '/settings',
    [
        SettingsController::class,
        'update',
    ]
)
    ->name(
        'settings.update'
    );
        }
    );