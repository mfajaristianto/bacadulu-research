<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Snapshot evidence sebuah AI Run. Dibuat sekali, tidak pernah diubah (updating ditolak); hanya hilang bila run-nya dihapus.
 * Tidak ada relasi ke dokumen/publikasi: salinan inilah yang menjadi bukti audit.
 */
class AiRunEvidence extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_run_evidence';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'authors_json' => 'array', 'allowed_claims_json' => 'array', 'relevance_json' => 'array', 'provenance_json' => 'array',
            'page_start' => 'integer', 'page_end' => 'integer', 'publication_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Snapshot evidence AI Run tidak boleh diubah.'));
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }

    /** Teks snapshot masih sama dengan hash saat disimpan. */
    public function intact(): bool
    {
        return $this->evidence_text === null
            ? $this->text_sha256 === null
            : hash_equals((string) $this->text_sha256, hash('sha256', $this->evidence_text));
    }
}
