<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiTool extends Model
{
    protected $table = 'ai_tools';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'active_prompt_template_id',
        'input_schema_json',
        'output_schema_json',
        'status',
    ];

    protected $casts = [
        'input_schema_json' => 'array',
        'output_schema_json' => 'array',
    ];

    public function activePromptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'active_prompt_template_id');
    }

    public function promptTemplates(): HasMany
    {
        return $this->hasMany(PromptTemplate::class);
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }
}
