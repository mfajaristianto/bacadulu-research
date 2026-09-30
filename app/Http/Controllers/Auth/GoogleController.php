<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    private const PENDING_LINK_SESSION_KEY =
        'bacadulu_google_pending_link';

    private const PENDING_LINK_TTL_MINUTES =
        10;

    /*
    |--------------------------------------------------------------------------
    | Redirect to Google
    |--------------------------------------------------------------------------
    */

    public function redirect(
        Request $request
    ): RedirectResponse {
        /*
         * Bersihkan pending linking lama.
         */
        $request
            ->session()
            ->forget(
                self::PENDING_LINK_SESSION_KEY
            );

        return Socialite::driver(
            'google'
        )->redirect();
    }


    /*
    |--------------------------------------------------------------------------
    | Google Callback
    |--------------------------------------------------------------------------
    */

    public function callback(
        Request $request
    ): RedirectResponse {
        try {
            $googleUser =
                Socialite::driver(
                    'google'
                )->user();
        } catch (Throwable $exception) {
            Log::warning(
                'Google OAuth callback failed.',
                [
                    'exception'
                        => $exception::class,
                ]
            );

            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Login Google gagal. Silakan coba lagi atau gunakan login email.',
                ]);
        }


        $googleId =
            trim(
                (string)
                    $googleUser
                        ->getId()
            );

        $email =
            Str::lower(
                trim(
                    (string)
                        $googleUser
                            ->getEmail()
                )
            );

        $googleName =
            trim(
                (string)
                    $googleUser
                        ->getName()
            );

        $googleAvatar =
            trim(
                (string)
                    $googleUser
                        ->getAvatar()
            );


        /*
         * Google wajib mengirim subject ID dan email.
         */
        if (
            $googleId === ''
            || $email === ''
        ) {
            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Google tidak mengirimkan informasi akun yang diperlukan.',
                ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CASE 1
        |--------------------------------------------------------------------------
        |
        | google_id sudah pernah tertaut.
        |
        | Ini adalah login Google normal.
        |
        */

        $linkedUser =
            User::query()
                ->where(
                    'google_id',
                    $googleId
                )
                ->first();

        if ($linkedUser) {
            if (
                $linkedUser->status
                !== 'active'
            ) {
                return redirect()
                    ->route(
                        'login'
                    )
                    ->withErrors([
                        'email'
                            => 'Akun ini tidak aktif. Silakan hubungi administrator.',
                    ]);
            }


            /*
             * Jangan overwrite avatar lokal user.
             *
             * Avatar Google hanya disimpan jika user
             * belum memiliki avatar sama sekali.
             */
            if (
                blank(
                    $linkedUser->avatar
                )
                && $googleAvatar !== ''
            ) {
                $linkedUser->forceFill([
                    'avatar'
                        => $googleAvatar,
                ])->save();
            }


            /*
             * Google OAuth membuktikan kepemilikan
             * Google account/email.
             */
            if (
                ! $linkedUser
                    ->email_verified_at
            ) {
                $linkedUser->forceFill([
                    'email_verified_at'
                        => now(),
                ])->save();
            }


            return $this
                ->loginAndRedirect(
                    $request,
                    $linkedUser
                );
        }


        /*
        |--------------------------------------------------------------------------
        | CASE 2
        |--------------------------------------------------------------------------
        |
        | Belum ada google_id.
        |
        | Sekarang cek apakah email sudah ada.
        |
        */

        $existingUser =
            User::query()
                ->where(
                    'email',
                    $email
                )
                ->first();


        /*
        |--------------------------------------------------------------------------
        | CASE 2A — NEW USER
        |--------------------------------------------------------------------------
        |
        | Email sama sekali belum ada.
        |
        | Buat akun baru dari Google.
        |
        */

        if (! $existingUser) {
            $user =
                User::create([
                    'name'
                        => $googleName !== ''
                            ? $googleName
                            : 'BacaDulu User',

                    'email'
                        => $email,

                    'password'
                        => null,

                    'google_id'
                        => $googleId,

                    'avatar'
                        => $googleAvatar !== ''
                            ? $googleAvatar
                            : null,

                    'email_verified_at'
                        => now(),

                    'status'
                        => 'active',
                ]);


            return $this
                ->loginAndRedirect(
                    $request,
                    $user,
                    true
                );
        }


        /*
        |--------------------------------------------------------------------------
        | EXISTING ACCOUNT STATUS
        |--------------------------------------------------------------------------
        */

        if (
            $existingUser->status
            !== 'active'
        ) {
            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Akun ini tidak aktif. Silakan hubungi administrator.',
                ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CASE 2B
        |--------------------------------------------------------------------------
        |
        | Email sudah ada dan google_id juga sudah terisi.
        |
        | Karena query google_id di atas tidak menemukan akun ini,
        | berarti google_id-nya berbeda.
        |
        | Jangan overwrite.
        |
        */

        if (
            filled(
                $existingUser
                    ->google_id
            )
        ) {
            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Email ini sudah terhubung dengan akun Google lain. Gunakan metode login yang sebelumnya digunakan.',
                ]);
        }


        /*
        |--------------------------------------------------------------------------
        | CASE 2C
        |--------------------------------------------------------------------------
        |
        | Existing user tetapi password kosong.
        |
        | Ini biasanya legacy OAuth account atau akun
        | yang memang tidak pernah memiliki password.
        |
        | Karena Google berhasil membuktikan kepemilikan
        | email yang sama, akun dapat ditautkan.
        |
        */

        if (
            blank(
                $existingUser
                    ->password
            )
        ) {
            $existingUser
                ->forceFill([
                    'google_id'
                        => $googleId,

                    'email_verified_at'
                        => $existingUser
                            ->email_verified_at
                            ?: now(),

                    'avatar'
                        => $existingUser
                            ->avatar
                            ?: (
                                $googleAvatar !== ''
                                    ? $googleAvatar
                                    : null
                            ),
                ])
                ->save();


            return $this
                ->loginAndRedirect(
                    $request,
                    $existingUser
                );
        }


        /*
        |--------------------------------------------------------------------------
        | CASE 2D — MANUAL ACCOUNT
        |--------------------------------------------------------------------------
        |
        | User pernah daftar dengan email/password.
        |
        | Jangan langsung menautkan Google hanya karena
        | email sama.
        |
        | User harus verifikasi password BacaDulu sekali.
        |
        */

        $request
            ->session()
            ->put(
                self::PENDING_LINK_SESSION_KEY,
                [
                    'user_id'
                        => $existingUser->id,

                    'email'
                        => $email,

                    'google_id'
                        => $googleId,

                    'google_name'
                        => $googleName,

                    'google_avatar'
                        => $googleAvatar,

                    'created_at'
                        => now()
                            ->timestamp,
                ]
            );


        return redirect()
            ->route(
                'google.link.form'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Google Account Linking Screen
    |--------------------------------------------------------------------------
    */

    public function linkForm(
        Request $request
    ): View|RedirectResponse {
        $pending =
            $this
                ->pendingLink(
                    $request
                );

        if (! $pending) {
            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Sesi penautan Google sudah berakhir. Silakan coba masuk dengan Google lagi.',
                ]);
        }


        $user =
            User::query()
                ->find(
                    $pending[
                        'user_id'
                    ]
                );


        if (
            ! $user
            || $user->status
                !== 'active'
        ) {
            $request
                ->session()
                ->forget(
                    self::PENDING_LINK_SESSION_KEY
                );

            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Akun tidak ditemukan atau tidak aktif.',
                ]);
        }


        return view(
            'auth.link-google',
            [
                'user'
                    => $user,

                'pendingGoogleEmail'
                    => $pending[
                        'email'
                    ],
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Google Linking
    |--------------------------------------------------------------------------
    */

    public function link(
        Request $request
    ): RedirectResponse {
        $data =
            $request
                ->validate([
                    'password'
                        => [
                            'required',
                            'string',
                        ],
                ]);


        $pending =
            $this
                ->pendingLink(
                    $request
                );


        if (! $pending) {
            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Sesi penautan Google sudah berakhir. Silakan coba masuk dengan Google lagi.',
                ]);
        }


        $user =
            User::query()
                ->find(
                    $pending[
                        'user_id'
                    ]
                );


        if (
            ! $user
            || $user->status
                !== 'active'
        ) {
            $request
                ->session()
                ->forget(
                    self::PENDING_LINK_SESSION_KEY
                );

            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Akun tidak ditemukan atau tidak aktif.',
                ]);
        }


        /*
         * Email harus masih sama.
         */
        if (
            Str::lower(
                trim(
                    (string)
                        $user->email
                )
            )
            !==
            Str::lower(
                trim(
                    (string)
                        $pending[
                            'email'
                        ]
                )
            )
        ) {
            $request
                ->session()
                ->forget(
                    self::PENDING_LINK_SESSION_KEY
                );

            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Informasi akun berubah. Silakan mulai proses Google login kembali.',
                ]);
        }


        /*
         * Kalau di antara callback dan form ini
         * akun ternyata sudah tertaut, hentikan.
         */
        if (
            filled(
                $user
                    ->google_id
            )
        ) {
            $request
                ->session()
                ->forget(
                    self::PENDING_LINK_SESSION_KEY
                );

            return redirect()
                ->route(
                    'login'
                )
                ->withErrors([
                    'email'
                        => 'Akun ini sudah terhubung dengan Google. Silakan masuk kembali.',
                ]);
        }


        /*
         * Password wajib cocok untuk akun manual.
         */
        if (
            blank(
                $user
                    ->password
            )
            || ! Hash::check(
                $data[
                    'password'
                ],
                $user->password
            )
        ) {
            return back()
                ->withErrors([
                    'password'
                        => 'Password BacaDulu tidak sesuai.',
                ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Attach Google
        |--------------------------------------------------------------------------
        */

        $user
            ->forceFill([
                'google_id'
                    => $pending[
                        'google_id'
                    ],

                'email_verified_at'
                    => $user
                        ->email_verified_at
                        ?: now(),

                'avatar'
                    => $user
                        ->avatar
                        ?: (
                            filled(
                                $pending[
                                    'google_avatar'
                                ]
                            )
                                ? $pending[
                                    'google_avatar'
                                ]
                                : null
                        ),
            ])
            ->save();


        $request
            ->session()
            ->forget(
                self::PENDING_LINK_SESSION_KEY
            );


        return $this
            ->loginAndRedirect(
                $request,
                $user,
                false,
                'Akun Google berhasil dihubungkan.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Login + Profile Gate
    |--------------------------------------------------------------------------
    */

    private function loginAndRedirect(
        Request $request,
        User $user,
        bool $newGoogleUser = false,
        ?string $message = null
    ): RedirectResponse {
        Auth::login(
            $user,
            true
        );


        $request
            ->session()
            ->regenerate();


        $user
            ->forceFill([
                'last_login_at'
                    => now(),
            ])
            ->save();


        $profileGate =
            (int)
                config(
                    'research.profile_gate',
                    80
                );


        /*
         * User Google baru SELALU diarahkan
         * ke profile setup.
         *
         * Ini membuat onboarding eksplisit.
         */
        if ($newGoogleUser) {
            return redirect()
                ->route(
                    'profile.setup'
                )
                ->with(
                    'success',
                    'Akun Google berhasil dibuat. Lengkapi profil Anda hingga minimal '.$profileGate.'% untuk membuka Research Workspace.'
                );
        }


        /*
         * Existing user tetap harus melewati
         * profile completion gate.
         */
        if (
            $user
                ->profileCompletion()
            < $profileGate
        ) {
            return redirect()
                ->route(
                    'profile.setup'
                )
                ->with(
                    'success',
                    $message
                        ?: 'Lengkapi profil Anda hingga minimal '.$profileGate.'% untuk melanjutkan ke Research Workspace.'
                );
        }


        $response =
            redirect()
                ->route(
                    'dashboard'
                );


        if ($message) {
            $response
                ->with(
                    'success',
                    $message
                );
        }


        return $response;
    }


    /*
    |--------------------------------------------------------------------------
    | Pending Google Link Session
    |--------------------------------------------------------------------------
    */

    private function pendingLink(
        Request $request
    ): ?array {
        $pending =
            $request
                ->session()
                ->get(
                    self::PENDING_LINK_SESSION_KEY
                );


        if (
            ! is_array(
                $pending
            )
        ) {
            return null;
        }


        $requiredKeys = [
            'user_id',
            'email',
            'google_id',
            'created_at',
        ];


        foreach ($requiredKeys as $key) {
            if (
                ! array_key_exists(
                    $key,
                    $pending
                )
            ) {
                return null;
            }
        }


        $createdAt =
            (int)
                $pending[
                    'created_at'
                ];


        $expiresAt =
            $createdAt
            + (
                self::PENDING_LINK_TTL_MINUTES
                * 60
            );


        if (
            now()->timestamp
            > $expiresAt
        ) {
            $request
                ->session()
                ->forget(
                    self::PENDING_LINK_SESSION_KEY
                );

            return null;
        }


        return $pending;
    }
}