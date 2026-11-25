<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalSyncRun extends Model
{
    /** Memegang slot konkurensi (antre di worker, berjalan, atau menunggu cooldown penyedia). */
    public const ACTIVE = ['queued', 'running', 'rate_limited'];

    /** Belum selesai: aktif + menunggu giliran dalam grup + dijeda. Dipakai untuk mencegah run ganda. */
    public const OPEN = ['queued', 'running', 'rate_limited', 'waiting', 'paused'];

    protected $guarded = [];

    protected $casts = [
        'scope_json' => 'array', 'checkpoint_json' => 'array', 'errors_json' => 'array',
        'started_at' => 'datetime', 'finished_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    /** Aktif tetapi tidak ada kemajuan (updated_at tak bergerak) melewati batas: worker kemungkinan mati. Aman dilanjutkan; job lama usang oleh token. */
    public function isStale(): bool
    {
        // rate_limited yang menunggu cooldown (Retry-After) sah menunggu lama, bukan macet.
        return $this->isActive() && $this->status !== 'rate_limited' && $this->updated_at !== null
            && $this->updated_at->lt(now()->subMinutes((int) config('external-sources.budget.stale_minutes', 10)));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
