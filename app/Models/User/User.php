<?php

namespace App\Models\User;

use App\Models\Billing\Subscription;
use App\Models\Channel\Channel;
use App\Models\Help\HelpArticleFeedback;
use App\Models\Identity\IdentityVerification;
use App\Models\Offer;
use App\Models\StudioContent;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Accès au back-office Filament (/admin) : réservé aux admins plateforme.
     * Remplace l'ancien middleware `admin` (contrôle sur la colonne `is_admin`).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Nom affiché dans Filament (menu utilisateur en haut à droite). Le modèle n'a
     * pas de colonne `name` : on le reconstruit depuis le profil, avec repli sur l'email.
     */
    public function getFilamentName(): string
    {
        $name = trim(($this->profile?->first_name ?? '').' '.($this->profile?->last_name ?? ''));

        return $name !== '' ? $name : $this->email;
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
        'locale',
        'email_verified_at',
        'status',
        'is_admin',
        'offer_id',
        'premium_until',
        'stripe_customer_id',
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
     * Attributs calculés toujours inclus dans la sérialisation JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_complete',
        'identity_verified',
        'is_premium',
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
            'identity_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'premium_until' => 'datetime',
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

    /**
     * Accesseur "profile_complete" — true si le profil renseigne tous les champs
     * requis pour débloquer les statistiques démographiques des sondages
     * (voir UserProfile::REQUIRED_FOR_COMPLETION).
     */
    public function getProfileCompleteAttribute(): bool
    {
        return $this->profile?->isComplete() ?? false;
    }

    /**
     * Sessions de vérification d'identité Didit du compte.
     */
    public function identityVerifications(): HasMany
    {
        return $this->hasMany(IdentityVerification::class);
    }

    /**
     * Accesseur "identity_verified" — true dès qu'une session Didit du compte
     * a été approuvée (colonne dénormalisée renseignée par le webhook).
     */
    public function getIdentityVerifiedAttribute(): bool
    {
        return $this->identity_verified_at !== null;
    }

    public function hasVerifiedIdentity(): bool
    {
        return $this->identity_verified_at !== null;
    }

    /**
     * Offre à laquelle l'utilisateur est rattaché (ligne réelle de `offers`, gérée
     * depuis le CRUD back-office) — NULL = freemium, pas d'offre payante liée.
     * Mise à jour soit par un abonnement Stripe payant (webhook, voir
     * App\Domain\Billing\Actions\HandleStripeWebhookAction), soit par une bascule
     * manuelle en back-office (compte offert, sans paiement).
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Accesseur "is_premium" — true si l'offre liée est payante et non expirée.
     * `premium_until` optionnel : passée cette date, l'utilisateur redevient freemium
     * sans qu'il faille détacher `offer_id`.
     */
    public function getIsPremiumAttribute(): bool
    {
        return $this->isPremium();
    }

    public function isPremium(): bool
    {
        if ($this->offer === null || (int) $this->offer->price_cents === 0) {
            return false;
        }

        return $this->premium_until === null || $this->premium_until->isFuture();
    }

    /** Abonnements Stripe du compte (historique inclus — voir Subscription::isActive()). */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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
     * Get channels where user is redactor
     */
    public function redactorChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
            ->wherePivot('role', 'redactor')
            ->withPivot(['role', 'subscribed_at', 'notifications_enabled']);
    }

    /**
     * Get channels where user is guest
     */
    public function guestChannels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_users')
            ->wherePivot('role', 'guest')
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
     * Favoris de l'utilisateur (lignes de pivot user_favorites, tout type confondu).
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    /**
     * Contenus studio mis en favori par l'utilisateur.
     */
    public function favoriteContents(): MorphToMany
    {
        return $this->morphedByMany(
            StudioContent::class,
            'favoritable',
            'user_favorites',
        )->withTimestamps();
    }

    /**
     * Votes "utile" / "pas utile" de l'utilisateur sur les articles du centre d'aide.
     */
    public function helpArticleFeedback(): HasMany
    {
        return $this->hasMany(HelpArticleFeedback::class);
    }

    /**
     * Historique de consultation de contenus (une ligne par contenu, upsert).
     */
    public function contentViews(): HasMany
    {
        return $this->hasMany(UserContentView::class);
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
