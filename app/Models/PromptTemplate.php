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
        'version',
        'system_prompt',
        'task_prompt',
        'retrieval_instructions',
        'output_instructions',
        'status',
        'created_by',
    ];

    public function aiTool(): BelongsTo
    {
        return $this->belongsTo(AiTool::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function aiRuns(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }
}
