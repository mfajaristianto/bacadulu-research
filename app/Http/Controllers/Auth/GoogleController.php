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
    private const PENDING_LINK_SESSION_KEY = 'bacadulu_google_pending_link';

    private const PENDING_LINK_TTL_MINUTES = 10;

    /**
     * Redirect user to Google OAuth.
     *
     * Google di BacaDulu Research hanya digunakan untuk login.
     * Pendaftaran akun baru tetap dilakukan melalui form register.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_LINK_SESSION_KEY);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Login Google gagal. Silakan coba lagi atau gunakan email dan kata sandi.',
                ]);
        }

        $googleId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $googleAvatar = trim((string) $googleUser->getAvatar());

        if ($googleId === '' || $email === '') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Google tidak mengirimkan informasi akun yang diperlukan.',
                ]);
        }

        /*
         * CASE 1
         * Google ID sudah pernah ditautkan.
         * Ini adalah login Google normal.
         */
        $linkedUser = User::query()
            ->where('google_id', $googleId)
            ->first();

        if ($linkedUser) {
            if ($linkedUser->status !== 'active') {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' => 'Akun ini tidak aktif. Silakan hubungi administrator.',
                    ]);
            }

            /*
             * Jangan overwrite avatar lokal.
             * Avatar Google hanya dipakai jika avatar user masih kosong.
             */
            if (blank($linkedUser->avatar) && $googleAvatar !== '') {
                $linkedUser->forceFill([
                    'avatar' => $googleAvatar,
                ])->save();
            }

            /*
             * Login Google membuktikan kepemilikan email Google.
             */
            if (! $linkedUser->email_verified_at) {
                $linkedUser->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            return $this->loginAndRedirect(
                $request,
                $linkedUser
            );
        }

        /*
         * CASE 2
         * Google ID belum tertaut.
         * Cari akun BacaDulu Research berdasarkan email.
         */
        $existingUser = User::query()
            ->where('email', $email)
            ->first();

        /*
         * CASE 2A
         * Email Google belum pernah terdaftar.
         *
         * PENTING:
         * JANGAN membuat user otomatis.
         * Semua pengguna wajib daftar terlebih dahulu.
         */
        if (! $existingUser) {
            $request->session()->forget(self::PENDING_LINK_SESSION_KEY);

            return redirect()
                ->route('register')
                ->withErrors([
                    'email' => 'Email Google tersebut belum terdaftar di BacaDulu Research. Silakan buat akun terlebih dahulu.',
                ])
                ->withInput([
                    'email' => $email,
                ]);
        }

        /*
         * Akun harus aktif.
         */
        if ($existingUser->status !== 'active') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Akun ini tidak aktif. Silakan hubungi administrator.',
                ]);
        }

        /*
         * CASE 2B
         * Email sudah terdaftar, tetapi google_id juga sudah terisi.
         *
         * Karena lookup berdasarkan google_id sebelumnya tidak menemukan
         * akun tersebut, google_id yang tersimpan pasti berbeda.
         * Jangan overwrite relasi Google yang sudah ada.
         */
        if (filled($existingUser->google_id)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Email ini sudah terhubung dengan akun Google lain. Gunakan metode login yang sebelumnya digunakan.',
                ]);
        }

        /*
         * CASE 2C
         * Legacy OAuth account.
         *
         * Akun sudah ada tetapi tidak memiliki password dan belum punya
         * google_id. Karena Google berhasil membuktikan email yang sama,
         * akun boleh langsung ditautkan.
         */
        if (blank($existingUser->password)) {
            $existingUser->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $existingUser->email_verified_at ?: now(),
                'avatar' => $existingUser->avatar ?: ($googleAvatar !== '' ? $googleAvatar : null),
            ])->save();

            return $this->loginAndRedirect(
                $request,
                $existingUser
            );
        }

        /*
         * CASE 2D
         * Akun manual email/password.
         *
         * Jangan langsung link Google hanya berdasarkan email.
         * User harus memasukkan password BacaDulu satu kali.
         */
        $request->session()->put(self::PENDING_LINK_SESSION_KEY, [
            'user_id' => $existingUser->id,
            'email' => $email,
            'google_id' => $googleId,
            'google_avatar' => $googleAvatar,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->route('google.link.form');
    }

    /**
     * Show Google linking confirmation form.
     */
    public function linkForm(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingLink($request);

        if (! $pending) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Sesi penautan Google sudah berakhir. Silakan coba masuk dengan Google lagi.',
                ]);
        }

        $user = User::query()->find($pending['user_id']);

        if (! $user || $user->status !== 'active') {
            $request->session()->forget(self::PENDING_LINK_SESSION_KEY);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Akun tidak ditemukan atau tidak aktif.',
                ]);
        }

        return view('auth.link-google', [
            'user' => $user,
            'pendingGoogleEmail' => $pending['email'],
        ]);
    }

    /**
     * Confirm Google account linking.
     */
    public function link(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => [
                'required',
                'string',
            ],
        ]);

        $pending = $this->pendingLink($request);

        if (! $pending) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Sesi penautan Google sudah berakhir. Silakan coba masuk dengan Google lagi.',
                ]);
        }

        $user = User::query()->find($pending['user_id']);

        if (! $user || $user->status !== 'active') {
            $request->session()->forget(self::PENDING_LINK_SESSION_KEY);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Akun tidak ditemukan atau tidak aktif.',
                ]);
        }

        /*
         * Pastikan email akun lokal tidak berubah selama proses linking.
         */
        if (
            Str::lower(trim((string) $user->email))
            !== Str::lower(trim((string) $pending['email']))
        ) {
            $request->session()->forget(self::PENDING_LINK_SESSION_KEY);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Informasi akun berubah. Silakan mulai proses Google login kembali.',
                ]);
        }

        /*
         * Jika google_id sudah terisi selama proses berlangsung,
         * hentikan linking agar tidak menimpa relasi existing.
         */
        if (filled($user->google_id)) {
            $request->session()->forget(self::PENDING_LINK_SESSION_KEY);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Akun ini sudah terhubung dengan Google. Silakan masuk kembali.',
                ]);
        }

        /*
         * Untuk akun manual, password lokal wajib benar.
         */
        if (
            blank($user->password)
            || ! Hash::check($data['password'], $user->password)
        ) {
            return back()
                ->withErrors([
                    'password' => 'Password BacaDulu tidak sesuai.',
                ])
                ->onlyInput([]);
        }

        /*
         * Attach Google account.
         */
        $user->forceFill([
            'google_id' => $pending['google_id'],
            'email_verified_at' => $user->email_verified_at ?: now(),
            'avatar' => $user->avatar ?: (
                filled($pending['google_avatar'] ?? null)
                    ? $pending['google_avatar']
                    : null
            ),
        ])->save();

        $request->session()->forget(self::PENDING_LINK_SESSION_KEY);

        return $this->loginAndRedirect(
            $request,
            $user,
            'Akun Google berhasil dihubungkan. Selanjutnya Anda dapat masuk menggunakan Google.'
        );
    }

    /**
     * Log user in and apply profile completion gate.
     */
    private function loginAndRedirect(
        Request $request,
        User $user,
        ?string $message = null
    ): RedirectResponse {
        Auth::login($user, true);

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $profileGate = (int) config(
            'research.profile_gate',
            80
        );

        /*
         * Semua user tetap wajib mencapai profile completion minimum.
         */
        if ($user->profileCompletion() < $profileGate) {
            return redirect()
                ->route('profile.setup')
                ->with(
                    'success',
                    $message
                        ?: 'Lengkapi profil Anda hingga minimal '.$profileGate.'% untuk melanjutkan ke Research Workspace.'
                );
        }

        $response = redirect()->route('dashboard');

        if ($message) {
            $response->with(
                'success',
                $message
            );
        }

        return $response;
    }

    /**
     * Read and validate temporary Google linking session.
     */
    private function pendingLink(Request $request): ?array
    {
        $pending = $request
            ->session()
            ->get(self::PENDING_LINK_SESSION_KEY);

        if (! is_array($pending)) {
            return null;
        }

        $requiredKeys = [
            'user_id',
            'email',
            'google_id',
            'created_at',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $pending)) {
                $request
                    ->session()
                    ->forget(self::PENDING_LINK_SESSION_KEY);

                return null;
            }
        }

        $createdAt = (int) $pending['created_at'];

        $expiresAt = $createdAt
            + (self::PENDING_LINK_TTL_MINUTES * 60);

        if (now()->timestamp > $expiresAt) {
            $request
                ->session()
                ->forget(self::PENDING_LINK_SESSION_KEY);

            return null;
        }

        return $pending;
    }
}
