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
        'version',
        'is_default',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'schema_json' =>
                'array',

            'version' =>
                'integer',

            'is_default' =>
                'boolean',
        ];
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

    public function scopeForType(
        Builder $query,
        string $documentType
    ): Builder {
        return $query->where(
            'document_type',
            $documentType
        );
    }

    public function scopeDefault(
        Builder $query
    ): Builder {
        return $query->where(
            'is_default',
            true
        );
    }
}