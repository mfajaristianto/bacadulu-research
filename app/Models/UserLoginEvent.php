<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginEvent extends Model
{
    protected $fillable = ['user_id', 'login_at', 'last_activity_at', 'ip_address', 'user_agent', 'device_type', 'os', 'browser'];

    protected function casts(): array
    {
        return ['login_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
