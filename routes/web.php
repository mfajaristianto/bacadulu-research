<?php

use App\Http\Controllers\Admin\AiToolController as AdminAiToolController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LibraryController as AdminLibraryController;
use App\Http\Controllers\Admin\PromptController as AdminPromptController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\ResearchDocumentController;
use App\Http\Controllers\ResearchFlowController;
use App\Http\Controllers\SavedResultController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::view('/', 'pages.landing')
    ->name('home');


/*
|--------------------------------------------------------------------------
| Guest Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')
    ->group(function () {
        Route::get(
            '/login',
            [
                AuthenticatedSessionController::class,
                'create',
            ]
        )->name('login');

        Route::post(
            '/login',
            [
                AuthenticatedSessionController::class,
                'store',
            ]
        )
            ->middleware('throttle:login')
            ->name('login.store');

        Route::get(
            '/register',
            [
                RegisteredUserController::class,
                'create',
            ]
        )->name('register');

        Route::post(
            '/register',
            [
                RegisteredUserController::class,
                'store',
            ]
        )
            ->middleware('throttle:register')
            ->name('register.store');

        if (config('research.google_auth_enabled', false)) {
            Route::get(
                '/auth/google',
                [
                    GoogleController::class,
                    'redirect',
                ]
            )->name('google.redirect');

            Route::get(
                '/auth/google/callback',
                [
                    GoogleController::class,
                    'callback',
                ]
            )->name('google.callback');
        }
    });


/*
|--------------------------------------------------------------------------
| Password Recovery
|--------------------------------------------------------------------------
|
| Password recovery also remains reachable from an authenticated
| Profile page.
|
*/

Route::get(
    '/forgot-password',
    [
        PasswordResetController::class,
        'requestForm',
    ]
)->name('password.request');

Route::post(
    '/forgot-password',
    [
        PasswordResetController::class,
        'sendResetLink',
    ]
)
    ->middleware('throttle:3,10')
    ->name('password.email');

Route::get(
    '/reset-password/{token}',
    [
        PasswordResetController::class,
        'resetForm',
    ]
)->name('password.reset');

Route::post(
    '/reset-password',
    [
        PasswordResetController::class,
        'reset',
    ]
)
    ->middleware('throttle:5,10')
    ->name('password.update');


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post(
    '/logout',
    [
        AuthenticatedSessionController::class,
        'destroy',
    ]
)
    ->middleware('auth')
    ->name('logout');


/*
|--------------------------------------------------------------------------
| Authenticated + Active User
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'active',
])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Registration Success
    |--------------------------------------------------------------------------
    */

    Route::view(
        '/register/success',
        'auth.register-success'
    )->name('register.success');


    /*
    |--------------------------------------------------------------------------
    | Onboarding
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/onboarding/complete',
        function (Request $request) {
            $request->user()->forceFill([
                'onboarding_completed_at' => now(),
            ])->save();

            return response()->json([
                'ok' => true,
            ]);
        }
    )
        ->middleware('throttle:10,1')
        ->name('onboarding.complete');


    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/email/verify',
        function () {
            return view('auth.verify-email');
        }
    )->name('verification.notice');

    Route::get(
        '/email/verify/{id}/{hash}',
        function (
            EmailVerificationRequest $request
        ) {
            $request->fulfill();

            return redirect()
                ->route('profile.setup')
                ->with(
                    'success',
                    'Email berhasil diverifikasi.'
                );
        }
    )
        ->middleware([
            'signed',
            'throttle:6,1',
        ])
        ->name('verification.verify');

    Route::post(
        '/email/verification-notification',
        function (
            Request $request
        ) {
            $request
                ->user()
                ->sendEmailVerificationNotification();

            return back()->with(
                'success',
                'Tautan verifikasi baru sudah dikirim.'
            );
        }
    )
        ->middleware('throttle:6,1')
        ->name('verification.send');
});


/*
|--------------------------------------------------------------------------
| Verified User Area
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'active',
    'verified.if.required',
])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profile/avatar',
        [
            ProfileController::class,
            'avatar',
        ]
    )->name('profile.avatar');

    Route::get(
        '/profile/setup',
        [
            ProfileController::class,
            'setup',
        ]
    )->name('profile.setup');

    Route::get(
        '/profile',
        [
            ProfileController::class,
            'edit',
        ]
    )->name('profile.edit');

    Route::patch(
        '/profile',
        [
            ProfileController::class,
            'update',
        ]
    )
        ->middleware('throttle:profile')
        ->name('profile.update');

    Route::patch(
        '/profile/credentials',
        [
            ProfileController::class,
            'updateCredentials',
        ]
    )
        ->middleware('throttle:20,15')
        ->name('profile.credentials.update');


    /*
    |--------------------------------------------------------------------------
    | User Area Requiring Complete Profile
    |--------------------------------------------------------------------------
    */

    Route::middleware('profile.complete')
        ->group(function () {
            /*
            |--------------------------------------------------------------------------
            | Dashboard / Workspace
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/dashboard',
                [
                    ResearchController::class,
                    'dashboard',
                ]
            )->name('dashboard');

            Route::get(
                '/workspace',
                [
                    ResearchController::class,
                    'workspace',
                ]
            )->name('workspace');

            // Alur produk: hasil riset ringkas (tanpa AI) -> Generate Document (AI).

            Route::get('/research/result', [ResearchFlowController::class, 'result'])
                ->middleware('throttle:60,1')
                ->name('research.result');

            Route::post('/research/document', [ResearchDocumentController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('research.document.store');

            Route::get(
                '/workspace/{conversation}',
                [
                    ResearchController::class,
                    'workspace',
                ]
            )->name('workspace.conversation');


            /*
            |--------------------------------------------------------------------------
            | Research
            |--------------------------------------------------------------------------
            */

            Route::post(
                '/research/run',
                [
                    ResearchController::class,
                    'run',
                ]
            )
                ->middleware('throttle:research')
                ->name('research.run');

            /*
            |--------------------------------------------------------------------------
            | Source Discovery (referensi tanpa AI)
            |--------------------------------------------------------------------------
            */

            Route::get('/research/references', [\App\Http\Controllers\ReferenceDiscoveryController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('research.references');

            Route::get('/research/references/{kind}/{id}', [\App\Http\Controllers\ReferenceDiscoveryController::class, 'show'])
                ->where(['kind' => 'ext|doc', 'id' => '[0-9]+'])
                ->middleware('throttle:60,1')
                ->name('research.references.show');

            Route::post('/research/references/analysis', [\App\Http\Controllers\ReferenceDiscoveryController::class, 'analysis'])
                ->middleware('throttle:research')
                ->name('research.references.analysis');

            // Sintesis AI berbasis Evidence Bundle (Tahap 16; hanya provider uji).
            Route::post('/research/references/synthesis', [\App\Http\Controllers\GroundedSynthesisController::class, 'store'])
                ->middleware('throttle:research')
                ->name('research.references.synthesis');

            Route::get('/research/references/synthesis/{uuid}', [\App\Http\Controllers\GroundedSynthesisController::class, 'show'])
                ->where('uuid', '[0-9a-fA-F-]{36}')
                ->middleware('throttle:60,1')
                ->name('research.references.synthesis.show');


            /*
            |--------------------------------------------------------------------------
            | Temporary User Library
            |--------------------------------------------------------------------------
            |
            | Masih dipertahankan sementara supaya behaviour existing tidak
            | langsung rusak. Navigation user sudah tidak mengarah ke sini.
            | Nanti kita retire setelah source/evidence flow selesai.
            |
            */

            Route::get(
                '/library',
                [
                    LibraryController::class,
                    'index',
                ]
            )
                ->middleware('throttle:library')
                ->name('library.index');

            Route::get(
                '/library/{document}',
                [
                    LibraryController::class,
                    'show',
                ]
            )
                ->middleware('throttle:library')
                ->name('library.show');


            /*
            |--------------------------------------------------------------------------
            | History
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/history',
                [
                    HistoryController::class,
                    'index',
                ]
            )->name('history.index');


            /*
            |--------------------------------------------------------------------------
            | Saved Results
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/saved',
                [
                    SavedResultController::class,
                    'index',
                ]
            )->name('saved.index');

            Route::post(
                '/saved',
                [
                    SavedResultController::class,
                    'store',
                ]
            )->name('saved.store');

            Route::get(
                '/saved/{savedResult}',
                [
                    SavedResultController::class,
                    'show',
                ]
            )
                ->whereNumber('savedResult')
                ->name('saved.show');

            Route::patch(
                '/saved/{savedResult}',
                [
                    SavedResultController::class,
                    'update',
                ]
            )
                ->whereNumber('savedResult')
                ->name('saved.update');

            Route::patch(
                '/saved/{savedResult}/favorite',
                [
                    SavedResultController::class,
                    'toggleFavorite',
                ]
            )
                ->whereNumber('savedResult')
                ->name('saved.favorite');

            Route::delete(
                '/saved/{savedResult}',
                [
                    SavedResultController::class,
                    'destroy',
                ]
            )
                ->whereNumber('savedResult')
                ->name('saved.destroy');


            /*
            |--------------------------------------------------------------------------
            | Conversation Management
            |--------------------------------------------------------------------------
            */

            Route::patch(
                '/research/conversations/{conversation}/pin',
                [
                    ConversationController::class,
                    'pin',
                ]
            )->name('conversations.pin');

            Route::patch(
                '/research/conversations/{conversation}/unpin',
                [
                    ConversationController::class,
                    'unpin',
                ]
            )->name('conversations.unpin');

            Route::patch(
                '/research/conversations/{conversation}/rename',
                [
                    ConversationController::class,
                    'rename',
                ]
            )->name('conversations.rename');

            Route::get(
                '/research/conversations/{conversation}/delete',
                [
                    ConversationController::class,
                    'deleteConfirmation',
                ]
            )->name('conversations.delete.confirmation');

            Route::delete(
                '/research/conversations/{conversation}',
                [
                    ConversationController::class,
                    'destroy',
                ]
            )->name('conversations.destroy');
        });
});


/*
|--------------------------------------------------------------------------
| LOCAL ERROR PAGE TESTER
|--------------------------------------------------------------------------
|
| KHUSUS DEVELOPMENT.
|
| Route ini sengaja berada DI LUAR:
| - middleware auth
| - middleware profile.complete
| - admin prefix
| - admin middleware
|
| Jadi URL test-nya benar-benar:
|
| /__test-error/404
| /__test-error/500
| dst.
|
| HAPUS SEBELUM PRODUCTION (guard environment di bawah juga membuat route
| ini tidak mungkin aktif di production).
|
*/

// Guard environment: tidak mungkin aktif di production.
if (app()->environment(['local', 'testing'])) {
    Route::get(
        '/__test-error/{code}',
        function (int $code) {
            $allowed = [
                403,
                404,
                419,
                422,
                429,
                500,
                502,
                503,
                504,
            ];

            abort_unless(
                in_array($code, $allowed, true),
                404
            );

            abort($code);
        }
    )->whereNumber('code');
}


/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

$adminPath = config(
    'research.admin_path'
);

Route::prefix($adminPath)
    ->name('admin.')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Admin Authentication
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/login',
            [
                AdminAuthController::class,
                'create',
            ]
        )->name('login');

        Route::post(
            '/login',
            [
                AdminAuthController::class,
                'store',
            ]
        )
            ->middleware('throttle:admin-login')
            ->name('login.store');

        Route::post(
            '/logout',
            [
                AdminAuthController::class,
                'destroy',
            ]
        )
            ->middleware('admin')
            ->name('logout');


        /*
        |--------------------------------------------------------------------------
        | Protected Admin Area
        |--------------------------------------------------------------------------
        */

        Route::middleware('admin')
            ->group(function () {
                /*
                |--------------------------------------------------------------------------
                | Dashboard
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/',
                    [
                        AdminDashboardController::class,
                        'index',
                    ]
                )->name('dashboard');

                // Filter Penggunaan AI di Beranda tanpa reload (JSON, hanya baca).
                Route::get(
                    '/ai-usage',
                    [
                        AdminDashboardController::class,
                        'usage',
                    ]
                )
                    ->middleware('throttle:60,1')
                    ->name('dashboard.ai-usage');


                /*
                |--------------------------------------------------------------------------
                | Library
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/library',
                    [
                        AdminLibraryController::class,
                        'index',
                    ]
                )->name('library.index');

                Route::get(
                    '/library/create',
                    [
                        AdminLibraryController::class,
                        'create',
                    ]
                )->name('library.create');

                Route::post(
                    '/library',
                    [
                        AdminLibraryController::class,
                        'store',
                    ]
                )->name('library.store');

                Route::get(
                    '/library/{document}/edit',
                    [
                        AdminLibraryController::class,
                        'edit',
                    ]
                )->name('library.edit');

                Route::put(
                    '/library/{document}',
                    [
                        AdminLibraryController::class,
                        'update',
                    ]
                )->name('library.update');

                // Duplikat, versi, dan penyajian PDF privat (hanya admin; tidak ada URL storage publik).
                Route::post('/library/check-duplicate', [AdminLibraryController::class, 'checkDuplicate'])
                    ->middleware('throttle:60,1')
                    ->name('library.check-duplicate');

                Route::get('/library/files/{file}/view', [AdminLibraryController::class, 'viewFile'])
                    ->middleware('throttle:120,1')
                    ->name('library.files.view');

                Route::get('/library/files/{file}/download', [AdminLibraryController::class, 'downloadFile'])
                    ->middleware('throttle:60,1')
                    ->name('library.files.download');

                Route::post('/library/{document}/files/{file}/restore', [AdminLibraryController::class, 'restore'])
                    ->middleware('throttle:20,1')
                    ->name('library.files.restore');


                /*
                |--------------------------------------------------------------------------
                | Research Tools
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/tools',
                    [
                        AdminAiToolController::class,
                        'index',
                    ]
                )->name('tools.index');

                Route::get('/tools/check', [AdminAiToolController::class, 'check'])->name('tools.check');

                Route::get(
                    '/tools/create',
                    [
                        AdminAiToolController::class,
                        'create',
                    ]
                )->name('tools.create');

                Route::post(
                    '/tools',
                    [
                        AdminAiToolController::class,
                        'store',
                    ]
                )->name('tools.store');

                Route::get(
                    '/tools/{tool}/edit',
                    [
                        AdminAiToolController::class,
                        'edit',
                    ]
                )->name('tools.edit');

                Route::put(
                    '/tools/{tool}',
                    [
                        AdminAiToolController::class,
                        'update',
                    ]
                )->name('tools.update');


                /*
                |--------------------------------------------------------------------------
                | Prompt Library
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/prompts',
                    [
                        AdminPromptController::class,
                        'index',
                    ]
                )->name('prompts.index');

                Route::get(
                    '/prompts/create',
                    [
                        AdminPromptController::class,
                        'create',
                    ]
                )->name('prompts.create');

                Route::post(
                    '/prompts',
                    [
                        AdminPromptController::class,
                        'store',
                    ]
                )->name('prompts.store');

                Route::get(
                    '/prompts/{prompt}/edit',
                    [
                        AdminPromptController::class,
                        'edit',
                    ]
                )->name('prompts.edit');

                Route::put(
                    '/prompts/{prompt}',
                    [
                        AdminPromptController::class,
                        'update',
                    ]
                )->name('prompts.update');

                // Instruksi & Versi: revisi (draf baru), aktivasi versi, arsip draf. Semua lewat PromptVersions.
                Route::post('/prompts/{prompt}/revise', [AdminPromptController::class, 'revise'])->name('prompts.revise');
                Route::patch('/prompts/{prompt}/activate', [AdminPromptController::class, 'activate'])->name('prompts.activate');
                Route::patch('/prompts/{prompt}/archive', [AdminPromptController::class, 'archive'])->name('prompts.archive');
            });
    });