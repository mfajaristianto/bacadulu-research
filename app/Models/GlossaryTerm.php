<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlossaryTerm extends Model
{
    protected $fillable = [
        'category_id',
        'term',
        'slug',
        'definition',
        'aliases_json',
        'context_note',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'aliases_json' =>
                'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'active'
        );
    }
}