<?php

namespace App\Models;

use App\Models\Channel\Channel;
use App\Models\User\User;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioContent extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'description',
        'status',
        'visibility',
        'slug',
        'pages',
        'blocks',
        'sections',
        'categories',
        'emoji',
        'coverage_type',
        'coverage_data',
        'published_as',
        'channel_id',
    ];

    protected $casts = [
        'pages' => 'array',
        'blocks' => 'array',
        'sections' => 'array',
        'categories' => 'array',
        'coverage_data' => 'array',
    ];

    /**
     * Get the user that owns the studio content.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the channel this content is published under (when published_as === 'channel').
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
