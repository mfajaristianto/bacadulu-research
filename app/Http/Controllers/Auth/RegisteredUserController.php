<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.portal', [
            'authView' => 'signup',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'institution' => [
                'required',
                'string',
                'min:2',
                'max:180',
            ],

            'role' => [
                'required',
                'string',
                Rule::in([
                    'student',
                    'university_student',
                    'teacher',
                    'lecturer',
                    'researcher',
                    'professional',
                    'practitioner',
                    'entrepreneur',
                    'editor_reviewer',
                    'education_staff',
                    'government_employee',
                    'other',
                ]),
            ],

            'domicile_city' => [
                'nullable',
                'string',
                'max:120',
            ],

            'research_interest' => [
                'nullable',
                'string',
                'max:120',
            ],

            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama lengkap minimal 2 karakter.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email belum valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk menggunakan akun tersebut.',

            'institution.required' => 'Institusi wajib diisi.',
            'institution.min' => 'Nama institusi minimal 2 karakter.',

            'role.required' => 'Peran utama wajib dipilih.',
            'role.in' => 'Peran yang dipilih tidak valid.',

            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi belum sama.',
        ]);

        $user = new User;

        $user->name = trim($data['name']);
        $user->email = strtolower(trim($data['email']));
        $user->institution = trim($data['institution']);
        $user->role = $data['role'];

        $user->domicile_city = filled($data['domicile_city'] ?? null)
            ? trim($data['domicile_city'])
            : null;

        $user->research_interest = filled($data['research_interest'] ?? null)
            ? trim($data['research_interest'])
            : null;

        $user->password = Hash::make($data['password']);
        $user->status = 'active';

        $user->save();

        if (config('research.require_email_verification', false)) {
            event(new Registered($user));
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()
            ->route('register.success')
            ->with(
                'success',
                'Akun BacaDulu Research berhasil dibuat.'
            );
    }
}
