<?php

namespace App\Models\Tv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvAudience extends Model
{
    protected $primaryKey = 'broadcast_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['broadcast_id', 'viewers', 'pda', 'rank', 'mediametrie_viewers'];

    protected $casts = [
        'viewers' => 'integer',
        'pda'     => 'float',
        'rank'    => 'integer',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(TvBroadcast::class, 'broadcast_id');
    }
}
