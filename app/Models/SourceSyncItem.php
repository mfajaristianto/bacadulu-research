<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceSyncItem extends Model
{
    protected $fillable = [
        'source_sync_run_id',
        'connector',
        'external_id',
        'title',
        'external_checksum',
        'previous_checksum',
        'remote_updated_at',
        'status',
        'category_id',
        'matched_document_id',
        'imported_document_id',
        'payload_json',
        'error_message',
        'reviewed_by',
        'reviewed_at',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' =>
                'array',

            'remote_updated_at' =>
                'datetime',

            'reviewed_at' =>
                'datetime',

            'imported_at' =>
                'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(
            SourceSyncRun::class,
            'source_sync_run_id'
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

    public function matchedDocument(): BelongsTo
    {
        return $this->belongsTo(
            Document::class,
            'matched_document_id'
        );
    }

    public function importedDocument(): BelongsTo
    {
        return $this->belongsTo(
            Document::class,
            'imported_document_id'
        );
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }
}