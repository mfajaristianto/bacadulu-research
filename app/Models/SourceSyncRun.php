<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceSyncRun extends Model
{
    protected $fillable = [
        'uuid',
        'connector',
        'status',
        'discovered_count',
        'new_count',
        'changed_count',
        'unchanged_count',
        'imported_count',
        'ignored_count',
        'error_message',
        'metadata_json',
        'initiated_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' =>
                'array',

            'started_at' =>
                'datetime',

            'finished_at' =>
                'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SourceSyncItem::class,
            'source_sync_run_id'
        );
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'initiated_by'
        );
    }
}