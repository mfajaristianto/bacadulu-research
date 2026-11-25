<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Template penulisan dokumen (Modul Prompt Dokumen). Struktur saja; isi prompt ada di prompt_templates lewat bagian. */
class DocumentPromptTemplate extends Model
{
    protected $fillable = [
        'uuid', 'name', 'slug', 'document_type', 'type_label', 'description', 'status', 'language', 'citation_style',
        'qa_mode', 'source_preset', 'example_json', 'created_by', 'activated_at',
    ];

    protected function casts(): array
    {
        return ['example_json' => 'array', 'activated_at' => 'datetime'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(DocumentPromptSection::class)->orderBy('position')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
