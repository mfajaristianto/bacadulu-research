<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'document_type',
        'description',
        'schema_json',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'schema_json'
                => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function generations(): HasMany
    {
        return $this->hasMany(
            DocumentGeneration::class
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