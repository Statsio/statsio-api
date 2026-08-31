<?php

namespace App\Models\Studio;

use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\StudioBlockResponseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudioBlockResponse extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return StudioBlockResponseFactory::new();
    }

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
