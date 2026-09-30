<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiRun extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'conversation_id',
        'ai_tool_id',
        'prompt_template_id',
        'status',
        'user_parameters_json',
        'model_provider',
        'model_name',
        'temperature',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'user_parameters_json'
                => 'array',

            'temperature'
                => 'float',

            'started_at'
                => 'datetime',

            'finished_at'
                => 'datetime',
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
            Conversation::class,
            'conversation_id'
        );
    }

    public function aiTool(): BelongsTo
    {
        return $this->belongsTo(
            AiTool::class
        );
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(
            PromptTemplate::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieved Sources
    |--------------------------------------------------------------------------
    |
    | Setelah retrieval selesai, pivot ini hanya berisi
    | source yang benar-benar menghasilkan evidence.
    |
    */

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            Document::class,
            'ai_run_sources',
            'ai_run_id',
            'document_id'
        )->withTimestamps();
    }

    public function results(): HasMany
    {
        return $this->hasMany(
            AiRunResult::class
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

    public function messages(): HasMany
    {
        return $this->hasMany(
            ResearchMessage::class,
            'ai_run_id'
        );
    }

    public function assistantMessage(): HasOne
    {
        return $this->hasOne(
            ResearchMessage::class,
            'ai_run_id'
        )
            ->where(
                'role',
                'assistant'
            )
            ->latestOfMany();
    }

    public function primaryResult(): HasOne
    {
        return $this->hasOne(
            AiRunResult::class
        )
            ->where(
                'result_type',
                'primary'
            )
            ->latestOfMany();
    }
}