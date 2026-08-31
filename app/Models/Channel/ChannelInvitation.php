<?php

namespace App\Models\Channel;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelInvitation extends Model
{
    protected $fillable = [
        'channel_id',
        'email',
        'role',
        'permissions',
        'token',
        'invited_by',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }
}
