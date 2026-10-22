<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'phone',
        'institution',
        'role',
        'domicile_city',
        'research_interest',
        'occupation',
        'address',
        'date_of_birth',
        'status',
        'last_login_at',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    public function conversations()
    {
        return $this->hasMany(
            Conversation::class
        );
    }

    public function aiRuns()
    {
        return $this->hasMany(
            AiRun::class
        );
    }

    public function savedResults()
    {
        return $this->hasMany(
            SavedResult::class
        );
    }

    public function profileCompletion(): int
    {
        $fields = [
            'name',
            'email',
            'phone',
            'institution',
            'address',
            'date_of_birth',
        ];

        $complete = collect($fields)
            ->filter(
                fn (string $field) => filled(
                    $this->{$field}
                )
            )
            ->count();

        return (int) round(
            ($complete / count($fields)) * 100
        );
    }

    public function missingProfileFields(): array
    {
        $labels = [
            'name' => 'Nama lengkap',
            'email' => 'Email',
            'phone' => 'Nomor telepon',
            'institution' => 'Institusi',
            'address' => 'Alamat',
            'date_of_birth' => 'Tanggal lahir',
        ];

        return collect($labels)
            ->reject(
                fn (
                    string $label,
                    string $field
                ) => filled($this->{$field})
            )
            ->values()
            ->all();
    }

    /*
     * Resolve avatar tanpa bergantung pada APP_URL.
     *
     * Avatar lokal diberikan melalui authenticated route
     * sehingga localhost, staging, dan production
     * menggunakan mekanisme yang sama.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $avatar = trim(
            (string) $this->avatar
        );

        if ($avatar === '') {
            return null;
        }

        /*
         * Avatar Google/external.
         */
        if (
            Str::startsWith(
                $avatar,
                ['http://', 'https://']
            )
        ) {
            return $avatar;
        }

        $normalized = ltrim(
            $avatar,
            '/'
        );

        $legacyStoragePath = Str::replaceStart(
            'storage/',
            '',
            $normalized
        );

        /*
         * ProfileController stores current device uploads in public/avatars.
         * The same authenticated URL is used in Profile, topbar and Workspace.
         */
        if (
            Str::startsWith($legacyStoragePath, 'avatars/')
            && Storage::disk('public')->exists($legacyStoragePath)
        ) {
            return route('profile.avatar', ['v' => $this->updated_at?->timestamp], false);
        }

        /*
         * Legacy:
         * storage/app/public/profile-avatars.
         */
        if (
            Str::startsWith(
                $legacyStoragePath,
                'profile-avatars/'
            )
            && Storage::disk('public')
                ->exists($legacyStoragePath)
        ) {
            return route(
                'profile.avatar',
                [
                    'v' => $this->updated_at?->timestamp,
                ],
                false
            );
        }

        $filename = Str::replaceStart(
            'profile-avatars/',
            '',
            $legacyStoragePath
        );

        /*
         * Current:
         * storage/app/private/profile-avatars.
         */
        if (
            $filename !== ''
            && Storage::disk('profile_avatars')
                ->exists($filename)
        ) {
            return route(
                'profile.avatar',
                [
                    'v' => $this->updated_at?->timestamp,
                ],
                false
            );
        }

        /*
         * Compatibility dengan project lama:
         * public/uploads/profile-avatars.
         */
        if (
            $filename !== ''
            && File::isFile(
                public_path(
                    'uploads/profile-avatars/'.$filename
                )
            )
        ) {
            return route(
                'profile.avatar',
                [
                    'v' => $this->updated_at?->timestamp,
                ],
                false
            );
        }

        return null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split(
            '/\s+/',
            trim((string) $this->name)
        ) ?: [];

        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(
                fn (string $part) => Str::upper(
                    Str::substr($part, 0, 1)
                )
            )
            ->implode('');

        return $initials !== ''
            ? $initials
            : 'B';
    }
}
