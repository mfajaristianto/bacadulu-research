<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRunCitation extends Model
{
    protected $table = 'ai_run_citations';

    protected $fillable = [
        'ai_run_id',
        'ai_run_result_id',
        'document_id',
        'document_chunk_id',
        'citation_key',
        'claim_label',
        'quote_snippet',
        'is_valid',
        'validation_message',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
            'metadata_json' => 'array',
        ];
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(
            AiRun::class
        );
    }

    public function aiRunResult(): BelongsTo
    {
        return $this->belongsTo(
            AiRunResult::class
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
