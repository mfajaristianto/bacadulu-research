<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRunRetrieval extends Model
{
    protected $table = 'ai_run_retrievals';

    protected $fillable = [
        'ai_run_id',
        'document_id',
        'document_chunk_id',
        'rank_no',
        'score',
        'retrieval_type',
        'citation_key',
        'excerpt',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'rank_no' => 'integer',
            'score' => 'float',
            'metadata_json' => 'array',
        ];
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(
            AiRun::class
        );
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            Document::class
        );
    }

    public function documentChunk(): BelongsTo
    {
        return $this->belongsTo(
            DocumentChunk::class,
            'document_chunk_id'
        );
    }
}
