<?php

use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\AnswerBehaviorController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CollectionController;
use App\Http\Controllers\Admin\DocumentPromptController;
use App\Http\Controllers\Admin\DocumentPromptImportController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\ExternalFulltextController;
use App\Http\Controllers\Admin\ExternalSourceController;
use App\Http\Controllers\Admin\GlossaryController;
use App\Http\Controllers\Admin\PromptVersionController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SourceIngestionStatusController;
use App\Http\Controllers\Admin\SourceSyncController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Rute {prompt} milik "Instruksi & Versi" (admin.prompts.*, admin.prompt-versions.*) hanya untuk instruksi alat riset. Versi Prompt
 * Dokumen (document-section:*) dan aturan Perilaku Jawaban AI (answer-policy:*) punya modul sendiri, jadi id-nya tidak boleh dibuka
 * lewat rute ini (404). Rute Prompt Dokumen juga memakai nama parameter {prompt} dengan id mentah, jadi tidak disentuh.
 */
Route::bind(
    'prompt',
    function (string $value, \Illuminate\Routing\Route $route) {
        $name = (string) $route->getName();

        if (! str_starts_with($name, 'admin.prompts.') && ! str_starts_with($name, 'admin.prompt-versions.')) {
            return $value;
        }

        return \App\Models\PromptTemplate::query()->toolInstructions()->findOrFail($value);
    }
);

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
                '/categories/check',
                [
                    CategoryController::class,
                    'check',
                ]
            )
                ->name(
                    'categories.check'
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

            // Anggota koleksi (muatan panel, pemilih sumber, tambah, lepas). Melepas hanya memutus relasi, dokumen tidak disentuh.
            Route::get('/collections/{collection}/members', [CollectionController::class, 'members'])->name('collections.members');
            Route::get('/collections/{collection}/candidates', [CollectionController::class, 'candidates'])->name('collections.candidates');
            Route::post('/collections/{collection}/documents', [CollectionController::class, 'attach'])->name('collections.attach');
            Route::delete('/collections/{collection}/documents/{document}', [CollectionController::class, 'detach'])->name('collections.detach');

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
            | Sumber Akademik Eksternal (metadata)
            |--------------------------------------------------------------------------
            */

            // Full-Text Acquisition (didaftarkan SEBELUM rute generik /{provider}/...). Tidak ada rute yang menyajikan berkas.
            Route::prefix('/external-sources/fulltext')->name('external-sources.fulltext.')->controller(ExternalFulltextController::class)->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/check', 'check')->name('check');
                Route::get('/{fulltext}', 'show')->name('show')->whereNumber('fulltext');
                Route::post('/{fulltext}/review', 'review')->name('review')->whereNumber('fulltext');
                Route::post('/{fulltext}/queue', 'queue')->name('queue')->whereNumber('fulltext');
                Route::post('/{fulltext}/cancel', 'cancel')->name('cancel')->whereNumber('fulltext');
                Route::post('/{fulltext}/retry', 'retry')->name('retry')->whereNumber('fulltext');
                Route::delete('/{fulltext}/copy', 'destroyCopy')->name('copy.delete')->whereNumber('fulltext');
            });

            Route::prefix('/external-sources')->name('external-sources.')->controller(ExternalSourceController::class)->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/status', 'status')->name('status');
                Route::post('/sync-all', 'syncAll')->name('sync-all');
                Route::put('/mappings', 'saveMapping')->name('mappings.save');
                Route::delete('/mappings/{mapping}', 'deleteMapping')->name('mappings.delete');
                Route::post('/runs/{run}/pause', 'pause')->name('runs.pause');
                Route::post('/groups/{group}/{action}', 'groupAction')->name('groups.action')->where(['group' => '[0-9a-f-]{36}', 'action' => 'pause|resume|cancel']);
                Route::post('/emergency-stop', 'emergencyStop')->name('emergency.stop');
                Route::post('/emergency-release', 'emergencyRelease')->name('emergency.release');
                Route::post('/publications/{publication}/doi-check', 'doiCheck')->name('publications.doi-check');
                Route::get('/{provider}/sets', 'sets')->name('sets')->where('provider', '[a-z0-9-]+');
                Route::get('/{provider}/since', 'since')->name('since')->where('provider', '[a-z0-9-]+');
                Route::post('/runs/{run}/cancel', 'cancel')->name('runs.cancel');
                Route::post('/runs/{run}/resume', 'resume')->name('runs.resume');
                Route::get('/publications/{publication}', 'show')->name('publications.show');
                Route::patch('/publications/{publication}/category', 'classify')->name('publications.classify');
                Route::post('/{provider}/settings', 'settings')->name('settings')->where('provider', '[a-z0-9-]+');
                Route::post('/{provider}/test', 'test')->name('test')->where('provider', '[a-z0-9-]+');
                Route::post('/{provider}/preview', 'preview')->name('preview')->where('provider', '[a-z0-9-]+');
                Route::post('/{provider}/sync', 'startSync')->name('sync')->where('provider', '[a-z0-9-]+');
            });

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

            Route::get('/glossary/check', [GlossaryController::class, 'check'])->name('glossary.check');

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

            // Template & Format Cetak: versi layout, default, preset, dan pratinjau (hanya membaca). Rute lama di atas tetap dipakai.
            Route::post('/document-templates/{documentTemplate}/restore', [DocumentTemplateController::class, 'restore'])->whereNumber('documentTemplate')->name('document-templates.restore');
            Route::post('/document-templates/{documentTemplate}/default', [DocumentTemplateController::class, 'setDefault'])->whereNumber('documentTemplate')->name('document-templates.default');
            Route::post('/document-templates/{documentTemplate}/adopt', [DocumentTemplateController::class, 'adopt'])->whereNumber('documentTemplate')->name('document-templates.adopt');
            Route::post('/document-templates/{documentTemplate}/versions/{version}/revise', [DocumentTemplateController::class, 'revise'])->whereNumber(['documentTemplate', 'version'])->name('document-templates.revise');
            Route::post('/document-templates/{documentTemplate}/versions/{version}/activate', [DocumentTemplateController::class, 'activate'])->whereNumber(['documentTemplate', 'version'])->name('document-templates.activate');
            Route::post('/document-templates/{documentTemplate}/versions/{version}/archive', [DocumentTemplateController::class, 'archiveVersion'])->whereNumber(['documentTemplate', 'version'])->name('document-templates.archive-version');
            Route::post('/document-templates/{documentTemplate}/preview', [DocumentTemplateController::class, 'preview'])->whereNumber('documentTemplate')->middleware('throttle:120,1')->name('document-templates.preview');
            Route::get('/document-templates/{documentTemplate}/versions/{version}/preview.pdf', [DocumentTemplateController::class, 'previewPdf'])->whereNumber(['documentTemplate', 'version'])->middleware('throttle:30,1')->name('document-templates.preview-pdf');
            Route::get('/document-templates/{documentTemplate}/versions/{version}/preview.docx', [DocumentTemplateController::class, 'previewDocx'])->whereNumber(['documentTemplate', 'version'])->middleware('throttle:30,1')->name('document-templates.preview-docx');

            /*
            |--------------------------------------------------------------------------
            | Provider & Analitik AI
            |--------------------------------------------------------------------------
            */

            Route::get('/ai-providers', [ProviderController::class, 'index'])->name('providers.index');
            Route::put('/ai-providers/config', [ProviderController::class, 'config'])->name('providers.config');
            Route::put('/ai-providers/synthesis', [ProviderController::class, 'synthesis'])->name('providers.synthesis');
            Route::post('/ai-providers/{provider}/test', [ProviderController::class, 'test'])->name('providers.test');

            // Prompt Dokumen: template -> bagian -> instruksi bagian (versi di prompt_templates). Rute memakai id mentah; lihat controller.
            Route::get('/prompt-documents', [DocumentPromptController::class, 'index'])->name('prompt-documents.index');
            Route::post('/prompt-documents', [DocumentPromptController::class, 'store'])->name('prompt-documents.store');
            Route::post('/prompt-documents/import', [DocumentPromptController::class, 'import'])->name('prompt-documents.import');
            // Impor dari Word: parse (unggah .docx, tidak menyimpan apa pun), cek nama, lalu simpan sebagai draf setelah ditinjau.
            Route::post('/prompt-documents/import-word/parse', [DocumentPromptImportController::class, 'parse'])->middleware('throttle:30,1')->name('prompt-documents.word.parse');
            Route::get('/prompt-documents/import-word/check-name', [DocumentPromptImportController::class, 'checkName'])->middleware('throttle:60,1')->name('prompt-documents.word.check-name');
            Route::post('/prompt-documents/import-word/save', [DocumentPromptImportController::class, 'save'])->middleware('throttle:30,1')->name('prompt-documents.word.save');
            Route::get('/prompt-documents/{template}', [DocumentPromptController::class, 'show'])->whereNumber('template')->name('prompt-documents.show');
            Route::put('/prompt-documents/{template}', [DocumentPromptController::class, 'update'])->whereNumber('template')->name('prompt-documents.update');
            Route::post('/prompt-documents/{template}/status', [DocumentPromptController::class, 'status'])->whereNumber('template')->name('prompt-documents.status');
            Route::post('/prompt-documents/{template}/duplicate', [DocumentPromptController::class, 'duplicate'])->whereNumber('template')->name('prompt-documents.duplicate');
            Route::post('/prompt-documents/{template}/activate-drafts', [DocumentPromptController::class, 'activateDrafts'])->whereNumber('template')->name('prompt-documents.activate-drafts');
            Route::post('/prompt-documents/{template}/sections', [DocumentPromptController::class, 'sectionStore'])->whereNumber('template')->name('prompt-documents.sections.store');
            Route::put('/prompt-documents/{template}/sections/{section}', [DocumentPromptController::class, 'sectionUpdate'])->whereNumber(['template', 'section'])->name('prompt-documents.sections.update');
            Route::post('/prompt-documents/{template}/sections/{section}/move', [DocumentPromptController::class, 'sectionMove'])->whereNumber(['template', 'section'])->name('prompt-documents.sections.move');
            Route::post('/prompt-documents/{template}/sections/{section}/prompt', [DocumentPromptController::class, 'promptStore'])->whereNumber(['template', 'section'])->name('prompt-documents.prompt.store');
            Route::put('/prompt-documents/{template}/sections/{section}/prompt/{prompt}', [DocumentPromptController::class, 'promptUpdate'])->whereNumber(['template', 'section', 'prompt'])->name('prompt-documents.prompt.update');
            Route::post('/prompt-documents/{template}/sections/{section}/prompt/{prompt}/revise', [DocumentPromptController::class, 'promptRevise'])->whereNumber(['template', 'section', 'prompt'])->name('prompt-documents.prompt.revise');
            Route::post('/prompt-documents/{template}/sections/{section}/prompt/{prompt}/activate', [DocumentPromptController::class, 'promptActivate'])->whereNumber(['template', 'section', 'prompt'])->name('prompt-documents.prompt.activate');
            Route::post('/prompt-documents/{template}/sections/{section}/prompt/{prompt}/archive', [DocumentPromptController::class, 'promptArchive'])->whereNumber(['template', 'section', 'prompt'])->name('prompt-documents.prompt.archive');

            // Perilaku Jawaban AI: aturan gaya, struktur, sumber, dan tindak lanjut. Versi memakai prompt_templates (answer-policy:{uuid}).
            Route::get('/answer-behavior', [AnswerBehaviorController::class, 'index'])->name('answer-behavior.index');
            Route::post('/answer-behavior', [AnswerBehaviorController::class, 'store'])->name('answer-behavior.store');
            Route::post('/answer-behavior/presets/{preset}', [AnswerBehaviorController::class, 'preset'])->where('preset', '[a-z0-9-]+')->name('answer-behavior.preset');
            Route::get('/answer-behavior/{rule}', [AnswerBehaviorController::class, 'show'])->whereUuid('rule')->name('answer-behavior.show');
            Route::put('/answer-behavior/{rule}/versions/{version}', [AnswerBehaviorController::class, 'update'])->whereUuid('rule')->whereNumber('version')->name('answer-behavior.update');
            Route::post('/answer-behavior/{rule}/versions/{version}/revise', [AnswerBehaviorController::class, 'revise'])->whereUuid('rule')->whereNumber('version')->name('answer-behavior.revise');
            Route::post('/answer-behavior/{rule}/versions/{version}/activate', [AnswerBehaviorController::class, 'activate'])->whereUuid('rule')->whereNumber('version')->name('answer-behavior.activate');
            Route::post('/answer-behavior/{rule}/versions/{version}/archive', [AnswerBehaviorController::class, 'archive'])->whereUuid('rule')->whereNumber('version')->name('answer-behavior.archive');
            Route::post('/answer-behavior/{rule}/deactivate', [AnswerBehaviorController::class, 'deactivate'])->whereUuid('rule')->name('answer-behavior.deactivate');

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

            // Aksi akun (Pengguna): reset tutorial, hapus riset, hapus akun (dengan halaman konfirmasi + ringkasan data terkait).
            Route::post('/users/{user}/tutorial-reset', [UserController::class, 'resetTutorial'])->whereNumber('user')->name('users.tutorial-reset');
            Route::get('/users/{user}/delete-research', [UserController::class, 'confirmResearchDeletion'])->whereNumber('user')->name('users.research.confirm');
            Route::delete('/users/{user}/research', [UserController::class, 'destroyResearch'])->whereNumber('user')->name('users.research.destroy');
            Route::get('/users/{user}/delete', [UserController::class, 'confirmDeletion'])->whereNumber('user')->name('users.delete.confirm');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');

            // Admin & Keamanan: daftar administrator. Hanya Super Admin yang boleh menambah/mengubah peran/mencabut.
            Route::get('/administrators', [AdministratorController::class, 'index'])->name('administrators.index');
            Route::post('/administrators', [AdministratorController::class, 'store'])->middleware('throttle:10,1')->name('administrators.store');
            Route::patch('/administrators/{user}', [AdministratorController::class, 'update'])->whereNumber('user')->name('administrators.update');
            Route::delete('/administrators/{user}', [AdministratorController::class, 'destroy'])->whereNumber('user')->name('administrators.destroy');

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

            // Detail satu event (JSON berisi HTML panel). Hanya baca; tidak ada rute ubah/hapus untuk audit.
            Route::get('/audit/{auditLog}', [AuditLogController::class, 'show'])
                ->whereNumber('auditLog')
                ->middleware('throttle:120,1')
                ->name('audit.show');

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

            // Detail satu event keamanan. Hanya baca; event di luar subset akses dijawab 404.
            Route::get('/security/events/{auditLog}', [SecurityController::class, 'show'])
                ->whereNumber('auditLog')
                ->middleware('throttle:120,1')
                ->name('security.show');
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