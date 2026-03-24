<?php

namespace App\Models\Channel;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User\User;

class Channel extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'status',
        'suspended_until',
        'anonymized_at'
    ];

    public function channelProfiles()
    {
        return $this->hasMany(ChannelProfile::class);
    }

    /**
     * Get the unique profile for this channel
     */
    public function profile(): HasOne
    {
        return $this->hasOne(ChannelProfile::class);
    }

    /**
     * Get or create the profile for this channel
     */
    public function getOrCreateProfile(array $data = []): ChannelProfile
    {
        return $this->profile()->firstOrCreate(
            ['channel_id' => $this->id],
            array_merge([
                'name' => 'Untitled Channel',
                'description' => null,
            ], $data)
        );
    }

    /**
     * Get all users associated with this channel
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_users')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled', 'is_banned', 'banned_until', 'ban_reason'])
                    ->withTimestamps();
    }

    /**
     * Get channel owner(s)
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_users')
                    ->wherePivot('role', 'owner')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get channel admins
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_users')
                    ->wherePivot('role', 'admin')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get channel moderators
     */
    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_users')
                    ->wherePivot('role', 'moderator')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get subscribed users
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_users')
                    ->whereNotNull('subscribed_at')
                    ->wherePivot('is_banned', false)
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get management team (owner, admin, moderator)
     */
    public function managementTeam(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_users')
                    ->whereIn('channel_users.role', ['owner', 'admin', 'moderator'])
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get banned users
     */
    public function bannedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'channel_users')
                    ->where('channel_users.is_banned', true)
                    ->where(function($query) {
                        $query->whereNull('channel_users.banned_until')
                              ->orWhere('channel_users.banned_until', '>', now());
                    })
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled', 'is_banned', 'banned_until', 'ban_reason']);
    }

    /**
     * Get subscriber count
     */
    public function getSubscriberCount(): int
    {
        return $this->subscribers()->count();
    }

    /**
     * Check if user is subscribed to this channel
     */
    public function isUserSubscribed(User $user): bool
    {
        return $this->users()
                    ->where('users.id', $user->id)
                    ->whereNotNull('channel_users.subscribed_at')
                    ->exists();
    }

    /**
     * Check if user is banned from this channel
     */
    public function isUserBanned(User $user): bool
    {
        return $this->users()
                    ->where('users.id', $user->id)
                    ->where('channel_users.is_banned', true)
                    ->where(function($query) {
                        $query->whereNull('channel_users.banned_until')
                              ->orWhere('channel_users.banned_until', '>', now());
                    })
                    ->exists();
    }

    /**
     * Get user's role in this channel
     */
    public function getUserRole(User $user): ?string
    {
        $pivot = $this->users()
                     ->where('users.id', $user->id)
                     ->first()
                     ?->pivot;

        return $pivot?->role;
    }
}
