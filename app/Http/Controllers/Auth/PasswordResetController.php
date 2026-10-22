<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function requestForm(Request $request): View
    {
        return view('auth.forgot-password', [
            'email' => old('email', $request->user()?->email),
        ]);
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        /*
         * Laravel's password broker intentionally uses the same neutral response
         * path whether the account exists or not, so this screen does not become
         * an account-enumeration endpoint.
         */
        $status = Password::sendResetLink([
            'email' => trim((string) $data['email']),
        ]);

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withErrors(['email' => 'Permintaan reset terlalu sering. Tunggu beberapa saat lalu coba lagi.'])
                ->onlyInput('email');
        }

        /* Do not reveal whether an address is registered. */
        return back()->with(
            'status',
            'Jika email tersebut terdaftar, tautan reset kata sandi akan dikirim ke inbox Anda.'
        );
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $status = Password::reset(
            [
                'email' => trim((string) $data['email']),
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => __($status)])
                ->withInput($request->only('email'));
        }

        return redirect()
            ->route('login')
            ->with('success', 'Kata sandi baru berhasil dibuat. Silakan masuk kembali.');
    }
}
