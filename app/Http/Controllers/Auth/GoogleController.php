<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed.', [
                'exception' => $exception::class,
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Login Google gagal. Silakan coba lagi atau gunakan login email.']);
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Google tidak mengirimkan alamat email akun.']);
        }

        $user = User::firstOrNew(['email' => $email]);

        if ($user->exists && $user->status !== 'active') {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun ini tidak aktif. Silakan hubungi administrator.']);
        }

        $user->fill([
            'name' => $googleUser->getName() ?: $user->name ?: 'BacaDulu User',
            'google_id' => $googleUser->getId(),
            'avatar' => $user->avatar ?: $googleUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?: now(),
            'status' => 'active',
        ]);
        $user->save();

        Auth::login($user);
        request()->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return $user->profileCompletion() < config('research.profile_gate', 80)
            ? redirect()->route('profile.setup')
            : redirect()->route('dashboard');
    }
}
