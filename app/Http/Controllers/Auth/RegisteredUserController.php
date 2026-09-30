<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view(
            'auth.register'
        );
    }


    public function store(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:120',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:users,email',
                ],

                'institution' => [
                    'nullable',
                    'string',
                    'max:180',
                ],

                'password' => [
                    'required',
                    'confirmed',
                    Rules\Password::defaults(),
                ],
            ]);


        $user =
            User::create([
                'name'
                    => trim(
                        $data[
                            'name'
                        ]
                    ),

                'email'
                    => strtolower(
                        trim(
                            $data[
                                'email'
                            ]
                        )
                    ),

                'institution'
                    => filled(
                        $data[
                            'institution'
                        ]
                        ?? null
                    )
                        ? trim(
                            $data[
                                'institution'
                            ]
                        )
                        : null,

                'password'
                    => Hash::make(
                        $data[
                            'password'
                        ]
                    ),

                'status'
                    => 'active',
            ]);


        if (
            config(
                'research.require_email_verification',
                false
            )
        ) {
            event(
                new Registered(
                    $user
                )
            );
        }


        Auth::login(
            $user
        );


        $request
            ->session()
            ->regenerate();


        return redirect()
            ->route(
                'profile.setup'
            );
    }
}