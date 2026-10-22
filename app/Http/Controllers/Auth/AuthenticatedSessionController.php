<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.portal', [
            'authView' => 'signin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
            ],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $email = strtolower(
            trim($data['email'])
        );

        /*
        |--------------------------------------------------------------------------
        | CEK APAKAH EMAIL PERNAH DAFTAR
        |--------------------------------------------------------------------------
        |
        | Database adalah source of truth.
        | Kalau email tidak ada di tabel users, user belum terdaftar.
        |
        */

        $user = User::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$email]
            )
            ->first();

        if (! $user) {
            return redirect()
                ->route('register')
                ->withErrors([
                    'email' => 'Email ini belum terdaftar. Silakan daftar terlebih dahulu untuk membuat akun BacaDulu Research.',
                ])
                ->withInput([
                    'email' => $email,
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS AKUN
        |--------------------------------------------------------------------------
        */

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'Akun ini sedang tidak aktif. Silakan hubungi administrator.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | LOGIN
        |--------------------------------------------------------------------------
        |
        | Gunakan email yang memang sudah tersimpan di database.
        |
        */

        $credentials = [
            'email' => $user->email,
            'password' => $data['password'],
            'status' => 'active',
        ];

        if (
            ! Auth::attempt(
                $credentials,
                $request->boolean('remember')
            )
        ) {
            throw ValidationException::withMessages([
                'password' => 'Kata sandi yang Anda masukkan tidak sesuai.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SESSION SECURITY
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();

        /*
        |--------------------------------------------------------------------------
        | LAST LOGIN
        |--------------------------------------------------------------------------
        */

        $user = Auth::user();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | PROFILE GATE
        |--------------------------------------------------------------------------
        */

        $profileGate = (int) config(
            'research.profile_gate',
            80
        );

        if (
            ! (config('research.profile_fields_optional', true)
                && filled($user->name)
                && filled($user->email)
                && filled($user->institution))
            && $user->profileCompletion() < $profileGate
        ) {
            return redirect()
                ->route('profile.setup')
                ->with(
                    'success',
                    'Login berhasil. Lengkapi profil Anda hingga minimal '.$profileGate.'% untuk melanjutkan ke Research Workspace.'
                );
        }

        return redirect()
            ->intended(
                route('dashboard')
            );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();

        return redirect()
            ->route('home');
    }
}
