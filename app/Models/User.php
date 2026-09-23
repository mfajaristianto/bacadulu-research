<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'phone',
        'institution',
        'occupation',
        'address',
        'date_of_birth',
        'status',
        'last_login_at',
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
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function aiRuns()
    {
        return $this->hasMany(AiRun::class);
    }

    public function savedResults()
    {
        return $this->hasMany(SavedResult::class);
    }

    public function profileCompletion(): int
    {
        $fields = ['name', 'email', 'phone', 'institution', 'address', 'date_of_birth'];
        $complete = collect($fields)
            ->filter(fn (string $field) => filled($this->{$field}))
            ->count();

        return (int) round(($complete / count($fields)) * 100);
    }

    public function missingProfileFields(): array
    {
        $labels = [
            'name' => 'Full name',
            'email' => 'Email address',
            'phone' => 'Phone number',
            'institution' => 'Institution',
            'address' => 'Address',
            'date_of_birth' => 'Date of birth',
        ];

        return collect($labels)
            ->reject(fn (string $label, string $field) => filled($this->{$field}))
            ->values()
            ->all();
    }

    /**
     * Resolve the avatar without depending on APP_URL.
     * Local avatars use an authenticated same-origin route so localhost ports,
     * staging hosts, and production hosts all work consistently.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $avatar = trim((string) $this->avatar);

        if ($avatar === '') {
            return null;
        }

        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }

        $normalized = ltrim($avatar, '/');
        $legacyPath = Str::replaceStart('storage/', '', $normalized);

        if (Str::startsWith($legacyPath, 'profile-avatars/') && Storage::disk('public')->exists($legacyPath)) {
            return route('profile.avatar', ['v' => $this->updated_at?->timestamp], false);
        }

        $newPath = Str::replaceStart('profile-avatars/', '', $legacyPath);

        if (Storage::disk('profile_avatars')->exists($newPath)) {
            return route('profile.avatar', ['v' => $this->updated_at?->timestamp], false);
        }

        return null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'B';
    }
}
