<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRunResult extends Model
{
    protected $table = 'ai_run_results';

    protected $fillable = [
        'ai_run_id',
        'result_type',
        'content',
        'structured_output_json',
        'citations_json',
    ];

    protected function casts(): array
    {
        return [
            'structured_output_json' => 'array',

            'citations_json' => 'array',
        ];
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(
            AiRun::class
        );
    }

    public function citations(): HasMany
    {
        return $this->hasMany(
            AiRunCitation::class,
            'ai_run_result_id'
        );
    }
}
