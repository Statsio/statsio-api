<?php

namespace App\Models\Studio;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioBlockResponse extends Model
{
    protected $fillable = [
        'studio_content_id',
        'block_id',
        'user_id',
        'respondent_token',
        'answer',
    ];

    protected $casts = [
        'answer' => 'array',
    ];

    public function studioContent(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
