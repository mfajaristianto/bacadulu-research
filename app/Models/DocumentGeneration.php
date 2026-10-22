<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGeneration extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'conversation_id',
        'ai_run_id',
        'saved_result_id',
        'document_template_id',
        'template_version',
        'document_type',
        'citation_style',
        'title',
        'content_json',
        'template_snapshot_json',
        'settings_snapshot_json',
        'glossary_snapshot_json',
        'status',
        'docx_disk',
        'docx_path',
        'pdf_disk',
        'pdf_path',
        'generated_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'content_json' =>
                'array',

            'template_snapshot_json' =>
                'array',

            'settings_snapshot_json' =>
                'array',

            'glossary_snapshot_json' =>
                'array',

            'template_version' =>
                'integer',

            'generated_at' =>
                'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            Conversation::class
        );
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(
            AiRun::class
        );
    }

    public function savedResult(): BelongsTo
    {
        return $this->belongsTo(
            SavedResult::class
        );
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            DocumentTemplate::class,
            'document_template_id'
        );
    }
}