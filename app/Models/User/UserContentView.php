<?php

namespace App\Models\User;

use App\Models\StudioContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserContentView extends Model
{
    protected $fillable = [
        'user_id',
        'studio_content_id',
        'last_viewed_at',
        'view_count',
        'progress',
    ];

    protected $casts = [
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
        'progress' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class, 'studio_content_id');
    }
}
