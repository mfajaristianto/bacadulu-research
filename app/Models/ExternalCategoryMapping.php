<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalCategoryMapping extends Model
{
    protected $guarded = [];

    protected $casts = ['terms_json' => 'array', 'filters_json' => 'array', 'enabled' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
