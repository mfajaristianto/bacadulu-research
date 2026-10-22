<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $table = 'documents';

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

        /*
        |--------------------------------------------------------------------------
        | Source Sync provenance
        |--------------------------------------------------------------------------
        */

        'source_connector',
        'external_source_id',
        'external_checksum',
        'last_synced_at',

        /*
        |--------------------------------------------------------------------------
        | Background ingestion
        |--------------------------------------------------------------------------
        */

        'status',
        'processing_stage',
        'processing_progress',
        'processing_file_id',
        'processing_error',
        'processing_started_at',
        'processing_finished_at',

        'visibility',
        'uploaded_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' =>
                'datetime',

            'last_synced_at' =>
                'datetime',

            'processing_progress' =>
                'integer',

            'processing_started_at' =>
                'datetime',

            'processing_finished_at' =>
                'datetime',
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

    public function collections(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Collection::class
            )
            ->withTimestamps();
    }

    public function sourceSyncItems(): HasMany
    {
        return $this->hasMany(
            SourceSyncItem::class,
            'imported_document_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Research Eligibility
    |--------------------------------------------------------------------------
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

    public function scopeEligibleForUsers(
        Builder $query
    ): Builder {
        return $query
            ->eligibleForResearch();
    }

    public function getIsRetrievalReadyAttribute(): bool
    {
        if (
            $this->status !== 'published'
            ||
            $this->visibility !== 'public'
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

    public function getIsProcessingAttribute(): bool
    {
        return $this->status === 'processing'
            &&
            in_array(
                $this->processing_stage,
                [
                    'queued',
                    'extracting',
                    'chunking',
                ],
                true
            );
    }
}