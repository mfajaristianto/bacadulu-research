<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    protected $table =
        'research_conversations';

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'status',
        'pinned_at',
    ];

    protected function casts(): array
    {
        return [
            'pinned_at'
                => 'datetime',

            'deleted_at'
                => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            ResearchMessage::class,
            'conversation_id'
        );
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(
            ResearchMessage::class,
            'conversation_id'
        )->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Direct AI Run Provenance
    |--------------------------------------------------------------------------
    */

    public function aiRuns(): HasMany
    {
        return $this->hasMany(
            AiRun::class,
            'conversation_id'
        );
    }

    public function documentGenerations(): HasMany
    {
        return $this->hasMany(
            DocumentGeneration::class,
            'conversation_id'
        );
    }
}