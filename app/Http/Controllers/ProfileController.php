<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user,
            403
        );

        $phone = $this->decryptNullable(
            $user->phone_encrypted
        );

        $address = $this->decryptNullable(
            $user->address_encrypted
        );

        $researchInterests = $this->decodeInterests(
            $user->research_interests
        );

        $avatarUrl = $user->avatar_url;

        $stats = [
            'research_sessions' =>
                $this->researchSessionCount(
                    $user->id
                ),

            'saved_projects' =>
                $this->savedResultCount(
                    $user->id
                ),

            'documents_exported' =>
                $this->documentGenerationCount(
                    $user->id
                ),
        ];

        $recentConversations =
            $this->recentConversations(
                $user->id
            );

        return view(
            'profile.edit',
            [
                'profileUser' =>
                    $user,

                'phone' =>
                    $phone,

                'address' =>
                    $address,

                'researchInterests' =>
                    $researchInterests,

                'avatarUrl' =>
                    $avatarUrl,

                'stats' =>
                    $stats,

                'recentConversations' =>
                    $recentConversations,
            ]
        );
    }

    public function setup(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user(),
            403
        );

        return redirect()
            ->route('profile.edit')
            ->with(
                'open_profile_editor',
                true
            );
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user,
            403
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:40',
            ],

            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'address' => [
                'nullable',
                'string',
                'max:1200',
            ],

            'bio' => [
                'nullable',
                'string',
                'max:600',
            ],

            'professional_role' => [
                'nullable',
                'string',
                'max:160',
            ],

            'institution' => [
                'nullable',
                'string',
                'max:190',
            ],

            'organization_type' => [
                'nullable',
                Rule::in([
                    'education',
                    'company',
                    'government',
                    'nonprofit',
                    'research',
                    'independent',
                    'other',
                ]),
            ],

            'division' => [
                'nullable',
                'string',
                'max:160',
            ],

            'department' => [
                'nullable',
                'string',
                'max:160',
            ],

            'organization_location' => [
                'nullable',
                'string',
                'max:190',
            ],

            'public_profile_url' => [
                'nullable',
                'url',
                'max:500',
            ],

            'research_interests_text' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'avatar' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $researchInterests =
            $this->parseInterests(
                $data['research_interests_text']
                ?? ''
            );

        $updates = [
            'name' =>
                trim(
                    $data['name']
                ),

            'phone_encrypted' =>
                filled(
                    $data['phone']
                    ?? null
                )
                    ? Crypt::encryptString(
                        trim(
                            $data['phone']
                        )
                    )
                    : null,

            'date_of_birth' =>
                filled(
                    $data['date_of_birth']
                    ?? null
                )
                    ? $data['date_of_birth']
                    : null,

            'address_encrypted' =>
                filled(
                    $data['address']
                    ?? null
                )
                    ? Crypt::encryptString(
                        trim(
                            $data['address']
                        )
                    )
                    : null,

            'bio' =>
                $this->nullableTrim(
                    $data['bio']
                    ?? null
                ),

            'professional_role' =>
                $this->nullableTrim(
                    $data['professional_role']
                    ?? null
                ),

            'institution' =>
                $this->nullableTrim(
                    $data['institution']
                    ?? null
                ),

            'organization_type' =>
                $this->nullableTrim(
                    $data['organization_type']
                    ?? null
                ),

            'division' =>
                $this->nullableTrim(
                    $data['division']
                    ?? null
                ),

            'department' =>
                $this->nullableTrim(
                    $data['department']
                    ?? null
                ),

            'organization_location' =>
                $this->nullableTrim(
                    $data['organization_location']
                    ?? null
                ),

            'public_profile_url' =>
                $this->nullableTrim(
                    $data['public_profile_url']
                    ?? null
                ),

            'research_interests' =>
                $researchInterests !== []
                    ? json_encode(
                        $researchInterests,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    )
                    : null,
        ];

        if (
            $request->hasFile(
                'avatar'
            )
        ) {
            $this->deleteOldLocalAvatar(
                $user->avatar
            );

            $path = $request
                ->file('avatar')
                ->store(
                    'avatars',
                    'public'
                );

            $updates['avatar'] =
                $path;
        }

        /*
         * forceFill sengaja dipakai supaya tidak
         * bergantung pada daftar $fillable User
         * yang sekarang.
         */
        $user
            ->forceFill(
                $updates
            )
            ->save();

        return redirect()
            ->route(
                'profile.edit'
            )
            ->with(
                'success',
                'Profil berhasil diperbarui.'
            );
    }

    /**
     * Sensitive account changes are protected by an explicit password check.
     * Stored passwords are hashes and are never exposed back to the browser.
     */
    public function updateCredentials(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        $rateKey = sprintf(
            'profile-credentials:%s:%s',
            $user->getAuthIdentifier(),
            $request->ip()
        );

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return back()
                ->withErrors([
                    'security' => "Terlalu banyak percobaan verifikasi yang gagal. Coba lagi dalam {$seconds} detik atau gunakan Lupa kata sandi.",
                ])
                ->withInput($request->except([
                    'current_password',
                    'new_password',
                    'new_password_confirmation',
                ]));
        }

        $data = $request->validate([
            'current_password' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'new_password' => [
                'nullable',
                'confirmed',
                PasswordRule::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        /*
         * Akun Google lama bisa saja belum memiliki password lokal.
         * Untuk kasus itu jangan membuat bypass: user diarahkan ke reset email.
         */
        if (
            blank($user->password)
            || ! Hash::check((string) $data['current_password'], (string) $user->password)
        ) {
            RateLimiter::hit($rateKey, 15 * 60);

            $remaining = max(0, 5 - RateLimiter::attempts($rateKey));

            return back()
                ->withErrors([
                    'current_password' => $remaining > 0
                        ? "Kata sandi saat ini tidak cocok. Sisa percobaan: {$remaining}."
                        : 'Verifikasi gagal 5 kali. Akses perubahan akun dikunci sementara selama 15 menit.',
                ])
                ->withInput([
                    'email' => $request->input('email'),
                ]);
        }

        RateLimiter::clear($rateKey);

        $newEmail = trim((string) $data['email']);
        $emailChanged = $newEmail !== (string) $user->email;
        $passwordChanged = filled($data['new_password'] ?? null);

        if (! $emailChanged && ! $passwordChanged) {
            return back()
                ->withErrors([
                    'security' => 'Tidak ada perubahan email atau kata sandi.',
                ])
                ->withInput([
                    'email' => $newEmail,
                ]);
        }

        if ($emailChanged) {
            $user->email = $newEmail;
            $user->email_verified_at = null;
        }

        if ($passwordChanged) {
            $user->password = Hash::make((string) $data['new_password']);
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()
            ->route('profile.edit')
            ->with(
                'success',
                $emailChanged
                    ? 'Akun berhasil diperbarui. Silakan verifikasi alamat email baru Anda.'
                    : 'Kata sandi berhasil diperbarui.'
            );
    }

    public function avatar(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user,
            403
        );

        $avatar = trim(
            (string) $user->avatar
        );

        abort_if(
            $avatar === '',
            404
        );

        if (
            Str::startsWith(
                $avatar,
                [
                    'https://',
                    'http://',
                ]
            )
        ) {
            return redirect()
                ->away(
                    $avatar
                );
        }

        $path =
            $this->normalizeAvatarPath(
                $avatar
            );

        if (! Storage::disk('public')->exists($path)) {
            $filename = basename(Str::replaceStart('profile-avatars/', '', $path));
            if ($filename !== '' && Storage::disk('profile_avatars')->exists($filename)) {
                return response()->file(Storage::disk('profile_avatars')->path($filename), [
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            }
            abort(404);
        }

        return response()
            ->file(
                Storage::disk('public')
                    ->path(
                        $path
                    ),
                [
                    'Cache-Control' =>
                        'private, max-age=3600',
                ]
            );
    }

    private function avatarUrl($user): ?string
    {
        $avatar = trim(
            (string) $user->avatar
        );

        if (
            $avatar === ''
        ) {
            return null;
        }

        if (
            Str::startsWith(
                $avatar,
                [
                    'https://',
                    'http://',
                ]
            )
        ) {
            return $avatar;
        }

        $path =
            $this->normalizeAvatarPath(
                $avatar
            );

        if (
            ! Storage::disk('public')
                ->exists(
                    $path
                )
        ) {
            return null;
        }

        return route(
            'profile.avatar'
        );
    }

    private function normalizeAvatarPath(
        string $avatar
    ): string {
        $avatar =
            ltrim(
                $avatar,
                '/'
            );

        $avatar =
            preg_replace(
                '#^(public/|storage/)#i',
                '',
                $avatar
            )
            ?? $avatar;

        return $avatar;
    }

    private function deleteOldLocalAvatar(
        ?string $avatar
    ): void {
        $avatar = trim(
            (string) $avatar
        );

        if (
            $avatar === ''
            || Str::startsWith(
                $avatar,
                [
                    'http://',
                    'https://',
                ]
            )
        ) {
            return;
        }

        $path =
            $this->normalizeAvatarPath(
                $avatar
            );

        if (
            Storage::disk('public')
                ->exists(
                    $path
                )
        ) {
            Storage::disk('public')
                ->delete(
                    $path
                );
        }
    }

    private function decryptNullable(
        ?string $value
    ): ?string {
        if (
            blank(
                $value
            )
        ) {
            return null;
        }

        try {
            return Crypt::decryptString(
                $value
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableTrim(
        mixed $value
    ): ?string {
        if (
            ! is_string(
                $value
            )
        ) {
            return null;
        }

        $value =
            trim(
                $value
            );

        return $value === ''
            ? null
            : $value;
    }

    private function parseInterests(
        string $value
    ): array {
        return collect(
            preg_split(
                '/[,;\n]+/',
                $value
            )
            ?: []
        )
            ->map(
                fn ($item) =>
                    trim(
                        (string) $item
                    )
            )
            ->filter()
            ->unique(
                fn ($item) =>
                    Str::lower(
                        $item
                    )
            )
            ->take(12)
            ->values()
            ->all();
    }

    private function decodeInterests(
        mixed $value
    ): array {
        if (
            is_array(
                $value
            )
        ) {
            return array_values(
                array_filter(
                    $value,
                    'is_string'
                )
            );
        }

        if (
            ! is_string(
                $value
            )
            || trim(
                $value
            ) === ''
        ) {
            return [];
        }

        $decoded =
            json_decode(
                $value,
                true
            );

        if (
            ! is_array(
                $decoded
            )
        ) {
            return [];
        }

        return array_values(
            array_filter(
                $decoded,
                'is_string'
            )
        );
    }

    private function researchSessionCount(
        int $userId
    ): int {
        try {
            if (
                Schema::hasTable(
                    'conversations'
                )
                && Schema::hasColumn(
                    'conversations',
                    'user_id'
                )
            ) {
                return DB::table(
                    'conversations'
                )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->count();
            }

            if (
                Schema::hasTable(
                    'ai_runs'
                )
                && Schema::hasColumn(
                    'ai_runs',
                    'user_id'
                )
            ) {
                return DB::table(
                    'ai_runs'
                )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->count();
            }
        } catch (Throwable) {
            //
        }

        return 0;
    }

    private function savedResultCount(
        int $userId
    ): int {
        try {
            if (
                Schema::hasTable(
                    'saved_results'
                )
                && Schema::hasColumn(
                    'saved_results',
                    'user_id'
                )
            ) {
                return DB::table(
                    'saved_results'
                )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->count();
            }
        } catch (Throwable) {
            //
        }

        return 0;
    }

    private function documentGenerationCount(
        int $userId
    ): int {
        try {
            if (
                ! Schema::hasTable(
                    'document_generations'
                )
            ) {
                return 0;
            }

            if (
                Schema::hasColumn(
                    'document_generations',
                    'user_id'
                )
            ) {
                return DB::table(
                    'document_generations'
                )
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->count();
            }

            if (
                Schema::hasColumn(
                    'document_generations',
                    'ai_run_id'
                )
                && Schema::hasTable(
                    'ai_runs'
                )
            ) {
                return DB::table(
                    'document_generations as dg'
                )
                    ->join(
                        'ai_runs as ar',
                        'ar.id',
                        '=',
                        'dg.ai_run_id'
                    )
                    ->where(
                        'ar.user_id',
                        $userId
                    )
                    ->count();
            }

            if (
                Schema::hasColumn(
                    'document_generations',
                    'saved_result_id'
                )
                && Schema::hasTable(
                    'saved_results'
                )
            ) {
                return DB::table(
                    'document_generations as dg'
                )
                    ->join(
                        'saved_results as sr',
                        'sr.id',
                        '=',
                        'dg.saved_result_id'
                    )
                    ->where(
                        'sr.user_id',
                        $userId
                    )
                    ->count();
            }
        } catch (Throwable) {
            //
        }

        return 0;
    }

    private function recentConversations(
        int $userId
    )
    {
        try {
            return Conversation::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->latest(
                    'updated_at'
                )
                ->limit(5)
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }
}