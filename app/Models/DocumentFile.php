<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    protected $table = 'document_files';

    protected $fillable = [
        'document_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'file_size',
        'checksum',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(
            Document::class
        );
    }
}
