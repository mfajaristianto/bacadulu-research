<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalPublication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'isbn_json' => 'array', 'issn_json' => 'array', 'authors_json' => 'array', 'keywords_json' => 'array',
        'published_date' => 'date', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(ExternalPublicationSource::class, 'publication_id');
    }

    public function categoryLinks(): HasMany
    {
        return $this->hasMany(ExternalPublicationCategory::class, 'publication_id');
    }
}
