<?php

namespace App\Models\User;

use App\Models\Channel\Channel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\StatsData\StatsDataDocument;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'password',
        'google_id',
        'email_verified_at',
        'status',
        'is_admin',
        'suspended_until',
        'anonymized_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'suspended_until' => 'datetime',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * Relation avec le profil utilisateur.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function statsDataDocuments(): HasMany
    {
        return $this->hasMany(StatsDataDocument::class);
    }

    /**
     * Get channels owned by this user
     */
    public function ownedChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
                    ->wherePivot('role', 'owner')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get channels where user is admin
     */
    public function adminChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
                    ->wherePivot('role', 'admin')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get channels where user is moderator
     */
    public function moderatorChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
                    ->wherePivot('role', 'moderator')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get channels user is subscribed to
     */
    public function subscribedChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
                    ->whereNotNull('subscribed_at')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get all channels user has access to (any role)
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
                    ->withPivot(['role', 'subscribed_at', 'notifications_enabled', 'is_banned', 'banned_until']);
    }

    /**
     * Vérifie si le compte est actif (non banni, non anonymisé, non suspendu)
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            if ($this->status === 'suspended' && $this->suspended_until && $this->suspended_until->isFuture()) {
                return false;
            }
            return false;
        }
        return true;
    }
}
