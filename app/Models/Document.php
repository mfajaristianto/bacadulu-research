<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $table = 'documents';

    protected $fillable = [
        'uuid',
        'title',
        'abstract',
        'category_id',
        'document_type',
        'publication_year',
        'language',
        'doi',
        'isbn',
        'publisher',
        'source_label',
        'status',
        'visibility',
        'uploaded_by',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeEligibleForUsers(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'));
    }
}
