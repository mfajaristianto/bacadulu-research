<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedResult extends Model
{
    protected $table = 'saved_results';

    protected $fillable = [
        'user_id',
        'ai_run_id',
        'title',
        'notes',
        'tags_json',
        'is_favorite',
    ];

    protected $casts = [
        'tags_json' => 'array',
        'is_favorite' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class);
    }
}
