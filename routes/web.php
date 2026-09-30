<?php

use App\Http\Controllers\Admin\AiToolController as AdminAiToolController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\LibraryController as AdminLibraryController;
use App\Http\Controllers\Admin\PromptController as AdminPromptController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\SavedResultController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view(
    '/',
    'pages.landing'
)->name('home');

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
    });

Route::post(
    '/logout',
    [
        AuthenticatedSessionController::class,
        'destroy',
    ]
)
    ->middleware('auth')
    ->name('logout');

Route::middleware([
    'auth',
    'active',
])->group(function () {
    Route::get(
        '/email/verify',
        function () {
            return view(
                'auth.verify-email'
            );
        }
    )->name(
        'verification.notice'
    );

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
        ->name(
            'verification.verify'
        );

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

Route::middleware([
    'auth',
    'active',
    'verified.if.required',
])->group(function () {
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

    Route::middleware(
        'profile.complete'
    )->group(function () {
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

        Route::get(
            '/workspace/{conversation}',
            [
                ResearchController::class,
                'workspace',
            ]
        )->name('workspace.conversation');

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
        )->name('saved.show');

        Route::patch(
            '/saved/{savedResult}',
            [
                SavedResultController::class,
                'update',
            ]
        )->name('saved.update');

        Route::patch(
            '/saved/{savedResult}/favorite',
            [
                SavedResultController::class,
                'toggleFavorite',
            ]
        )->name('saved.favorite');

        Route::delete(
            '/saved/{savedResult}',
            [
                SavedResultController::class,
                'destroy',
            ]
        )->name('saved.destroy');

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
        )->name(
            'conversations.delete.confirmation'
        );

        Route::delete(
            '/research/conversations/{conversation}',
            [
                ConversationController::class,
                'destroy',
            ]
        )->name('conversations.destroy');
    });
});

$adminPath = config(
    'research.admin_path'
);

Route::prefix($adminPath)
    ->name('admin.')
    ->group(function () {
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

        Route::middleware('admin')
            ->group(function () {
                Route::get(
                    '/',
                    [
                        AdminDashboardController::class,
                        'index',
                    ]
                )->name('dashboard');

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
            });
    });