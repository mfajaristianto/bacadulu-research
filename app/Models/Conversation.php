<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'research_conversations';

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            ResearchMessage::class,
            'conversation_id'
        );
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(
            ResearchMessage::class,
            'conversation_id'
        )->latestOfMany();
    }
}
