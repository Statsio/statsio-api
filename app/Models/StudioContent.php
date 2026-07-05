<?php

namespace App\Models;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioContent extends Model
{
    use HasFactory;

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
        'coverage_type',
        'coverage_data',
        'published_as',
        'channel_id',
    ];

    protected $casts = [
        'pages'         => 'array',
        'blocks'        => 'array',
        'sections'      => 'array',
        'categories'    => 'array',
        'coverage_data' => 'array',
    ];

    /**
     * Get the user that owns the studio content.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
