<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Potongan teks full-text eksternal. Terpisah dari document_chunks (Pustaka internal). */
class ExternalFulltextChunk extends Model
{
    protected $guarded = [];

    protected $casts = ['is_references' => 'boolean', 'provenance_json' => 'array'];

    public function fulltext(): BelongsTo
    {
        return $this->belongsTo(ExternalFulltext::class, 'fulltext_id');
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(ExternalPublication::class, 'publication_id');
    }
}
