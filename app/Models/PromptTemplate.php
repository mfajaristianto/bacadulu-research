<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptTemplate extends Model
{
    protected $table = 'prompt_templates';

    protected $fillable = [
        'ai_tool_id',
        'name',
        'indonesian_name',
        'short_description',
        'question_template',
        'meaning',
        'when_to_use',
        'when_not_to_use',
        'example_text',
        'expected_output',
        'version',
        'system_prompt',
        'task_prompt',
        'retrieval_instructions',
        'output_instructions',
        'status',
        'input_mode',
        'created_by',
    ];

    /** Instruksi alat riset saja; versi bagian Prompt Dokumen (nama berawalan document-section:) dikelola di modulnya. */
    public function scopeToolInstructions($query)
    {
        return $query->where('name', 'not like', DocumentPromptSection::PROMPT_PREFIX.'%')
            ->where('name', 'not like', \App\Services\Research\AnswerBehavior::PREFIX.'%');
    }

    public function aiTool(): BelongsTo
    {
        return $this->belongsTo(
            AiTool::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(
            AiRun::class
        );
    }

    /** 'book' = topik + N rumusan masalah. Kolom belum ada sebelum migration disetujui => selalu 'standard'. */
    public function getInputModeNameAttribute(): string
    {
        return ($this->attributes['input_mode'] ?? 'standard') === 'book' ? 'book' : 'standard';
    }

    /** Teks aturan (ruleset) Alat Riset: dibaca Research sebagai aturan dimensi, BUKAN prompt ke LLM. */
    public function rulesetText(): string
    {
        return trim(implode("\n", array_filter([$this->question_template, $this->task_prompt, $this->retrieval_instructions, $this->output_instructions, $this->short_description])));
    }

    public function getDisplayNameAttribute(): string
    {
        if (
            filled(
                $this->indonesian_name
            )
        ) {
            return $this->name
                .' — '
                .$this->indonesian_name;
        }

        return $this->name;
    }
}