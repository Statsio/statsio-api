<?php

namespace App\Models\StatsData;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatsDataDocumentShare extends Model
{
    protected $table = 'stats_data_document_shares';

    protected $fillable = [
        'stats_data_document_id',
        'user_id',
        'role',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(StatsDataDocument::class, 'stats_data_document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

