<?php

namespace App\Models;

use App\Services\FullText\FullTextState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/** Satu lokasi full-text sebuah publikasi eksternal beserta status pipeline-nya. */
class ExternalFulltext extends Model
{
    protected $guarded = [];

    protected $casts = [
        'evidence_json' => 'array', 'reviewed_at' => 'datetime', 'next_attempt_at' => 'datetime', 'locked_at' => 'datetime',
        'checked_at' => 'datetime', 'downloaded_at' => 'datetime', 'extracted_at' => 'datetime', 'indexed_at' => 'datetime',
        'size_bytes' => 'integer', 'chunk_count' => 'integer', 'attempts' => 'integer',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(ExternalPublication::class, 'publication_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ExternalFulltextChunk::class, 'fulltext_id');
    }

    /**
     * Perpindahan status atomik (compare-and-set): hanya berhasil bila baris MASIH berstatus $from. Dua worker,
     * pembatalan admin, atau job usang tidak bisa saling menimpa. Perpindahan yang tidak ada di tabel transisi ditolak.
     *
     * @return bool true bila baris ini yang berhasil memindahkan status
     */
    public function transition(string $from, string $to, array $attributes = []): bool
    {
        if (! FullTextState::canTransition($from, $to)) {
            throw new InvalidArgumentException("Perpindahan status {$from} -> {$to} tidak diizinkan.");
        }

        $values = $attributes + ['state' => $to, 'updated_at' => now()];
        $done = static::query()->whereKey($this->getKey())->where('state', $from)->update($values) === 1;

        if ($done) {
            $this->refresh();
        }

        return $done;
    }
}
