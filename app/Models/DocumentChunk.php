<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentChunk extends Model
{
    protected $table = 'document_chunks';

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            Document::class
        );
    }

    public function retrievals(): HasMany
    {
        return $this->hasMany(
            AiRunRetrieval::class,
            'document_chunk_id'
        );
    }

    public function citations(): HasMany
    {
        return $this->hasMany(
            AiRunCitation::class,
            'document_chunk_id'
        );
    }
}
