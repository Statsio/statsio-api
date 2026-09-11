<?php

namespace App\Models\Studio;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioContentComment extends Model
{
    protected $fillable = [
        'studio_content_id',
        'user_id',
        'body',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class, 'studio_content_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
