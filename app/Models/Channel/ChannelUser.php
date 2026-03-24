<?php

namespace App\Models\Channel;

use App\Models\User\User;
use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'user_id',
        'role',
        'subscribed_at',
        'notifications_enabled',
        'is_banned',
        'banned_until',
        'ban_reason',
    ];

    protected $casts = [
        'role' => ChannelUserRoleEnum::class,
        'subscribed_at' => 'datetime',
        'notifications_enabled' => 'boolean',
        'is_banned' => 'boolean',
        'banned_until' => 'datetime',
    ];

    /**
     * Get the channel this user belongs to
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the user who belongs to this channel
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user is subscribed to the channel
     */
    public function isSubscribed(): bool
    {
        return $this->subscribed_at !== null;
    }

    /**
     * Check if user is currently banned
     */
    public function isCurrentlyBanned(): bool
    {
        if (!$this->is_banned) {
            return false;
        }

        if ($this->banned_until && $this->banned_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Ban user from channel
     */
    public function ban(?string $reason = null, ?\DateTime $until = null): void
    {
        $this->update([
            'is_banned' => true,
            'banned_until' => $until,
            'ban_reason' => $reason,
        ]);
    }

    /**
     * Unban user from channel
     */
    public function unban(): void
    {
        $this->update([
            'is_banned' => false,
            'banned_until' => null,
            'ban_reason' => null,
        ]);
    }

    /**
     * Subscribe user to channel
     */
    public function subscribe(): void
    {
        $this->update([
            'subscribed_at' => now(),
        ]);
    }

    /**
     * Unsubscribe user from channel
     */
    public function unsubscribe(): void
    {
        $this->update([
            'subscribed_at' => null,
        ]);
    }

    /**
     * Toggle notifications for this channel
     */
    public function toggleNotifications(): void
    {
        $this->update([
            'notifications_enabled' => !$this->notifications_enabled,
        ]);
    }

    /**
     * Check if user can manage the channel
     */
    public function canManageChannel(): bool
    {
        return $this->role?->canManageChannel() ?? false;
    }

    /**
     * Check if user can moderate content
     */
    public function canModerate(): bool
    {
        return $this->role?->canModerate() ?? false;
    }

    /**
     * Check if user is admin or owner
     */
    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    /**
     * Check if user is owner
     */
    public function isOwner(): bool
    {
        return $this->role?->isOwner() ?? false;
    }

    /**
     * Scope to get only subscribed users
     */
    public function scopeSubscribed($query)
    {
        return $query->whereNotNull('subscribed_at');
    }

    /**
     * Scope to get only banned users
     */
    public function scopeBanned($query)
    {
        return $query->where('is_banned', true)
                    ->where(function($q) {
                        $q->whereNull('banned_until')
                          ->orWhere('banned_until', '>', now());
                    });
    }

    /**
     * Scope to get users by role
     */
    public function scopeByRole($query, ChannelUserRoleEnum $role)
    {
        return $query->where('role', $role->value);
    }

    /**
     * Scope to get management team (owner, admin, moderator)
     */
    public function scopeManagementTeam($query)
    {
        return $query->whereIn('role', [
            ChannelUserRoleEnum::OWNER->value,
            ChannelUserRoleEnum::ADMIN->value,
            ChannelUserRoleEnum::MODERATOR->value,
        ]);
    }
}
