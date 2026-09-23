<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRun extends Model
{
    protected $table = 'ai_runs';

    protected $fillable = [
        'uuid',
        'user_id',
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

    protected $casts = [
        'user_parameters_json' => 'array',
        'temperature' => 'float',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiTool(): BelongsTo
    {
        return $this->belongsTo(AiTool::class);
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }
}
