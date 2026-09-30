<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $table =
        'documents';

    protected $fillable = [
        'uuid',
        'title',
        'abstract',
        'category_id',
        'document_type',
        'publication_year',
        'language',
        'doi',
        'isbn',
        'publisher',
        'source_label',
        'status',
        'visibility',
        'uploaded_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at'
                => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

    public function files(): HasMany
    {
        return $this->hasMany(
            DocumentFile::class
        );
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(
            DocumentChunk::class
        );
    }

    public function retrievals(): HasMany
    {
        return $this->hasMany(
            AiRunRetrieval::class
        );
    }

    public function citations(): HasMany
    {
        return $this->hasMany(
            AiRunCitation::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieval Eligibility
    |--------------------------------------------------------------------------
    |
    | Source hanya boleh masuk candidate retrieval jika:
    |
    | 1. published
    | 2. public
    | 3. category aktif
    | 4. benar-benar sudah mempunyai chunks
    |
    | Dengan demikian PDF yang gagal parse atau source metadata yang
    | belum selesai diproses tidak ikut Research Workspace.
    |
    */

    public function scopeEligibleForResearch(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'status',
                'published'
            )
            ->where(
                'visibility',
                'public'
            )
            ->whereHas(
                'chunks'
            )
            ->whereHas(
                'category',
                fn (
                    Builder $category
                ) =>
                    $category
                        ->where(
                            'status',
                            'active'
                        )
            );
    }

    /*
     * Compatibility untuk kode lama.
     */
    public function scopeEligibleForUsers(
        Builder $query
    ): Builder {
        return $query
            ->eligibleForResearch();
    }

    public function getIsRetrievalReadyAttribute(): bool
    {
        if (
            $this->status
            !== 'published'
        ) {
            return false;
        }

        if (
            $this->visibility
            !== 'public'
        ) {
            return false;
        }

        if (
            $this->relationLoaded(
                'chunks'
            )
        ) {
            return $this
                ->chunks
                ->isNotEmpty();
        }

        return $this
            ->chunks()
            ->exists();
    }
}