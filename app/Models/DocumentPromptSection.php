<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu bagian dokumen. Instruksinya = baris prompt_templates bernama promptName() (ai_tool_id NULL), versi efektif lewat penunjuk. */
class DocumentPromptSection extends Model
{
    public const PROMPT_PREFIX = 'document-section:';

    protected $fillable = [
        'uuid', 'document_prompt_template_id', 'key', 'title', 'description', 'purpose', 'position', 'is_required', 'is_enabled',
        'role', 'min_words', 'max_words', 'target_words', 'item_count', 'min_sources', 'requirements', 'depends_on',
        'active_prompt_template_id',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_enabled' => 'boolean', 'depends_on' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentPromptTemplate::class, 'document_prompt_template_id');
    }

    public function activePrompt(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'active_prompt_template_id');
    }

    public function promptName(): string
    {
        return self::PROMPT_PREFIX.$this->uuid;
    }
}
