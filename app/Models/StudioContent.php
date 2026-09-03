<?php

namespace App\Models;

use App\Models\Channel\Channel;
use App\Models\Content\Dossier;
use App\Models\Studio\StudioBlockResponse;
use App\Models\Studio\StudioContentVersion;
use App\Models\User\User;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudioContent extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'survey_kind',
        'requires_identity_verification',
        'petition_goal',
        'petition_target',
        'description',
        'status',
        'slug',
        'pages',
        'blocks',
        'sections',
        'categories',
        'card_block_id',
        'coverage',
        'published_as',
        'channel_id',
        'response_deadline',
        'published_version_id',
        'published_version',
        'first_published_at',
        'last_published_at',
    ];

    protected $casts = [
        'pages' => 'array',
        'blocks' => 'array',
        'sections' => 'array',
        'categories' => 'array',
        'views_count' => 'integer',
        'published_version' => 'integer',
        'response_deadline' => 'datetime',
        'first_published_at' => 'datetime',
        'last_published_at' => 'datetime',
        'requires_identity_verification' => 'boolean',
        'petition_goal' => 'integer',
    ];

    /**
     * Get the user that owns the studio content.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the channel this content is published under (when published_as === 'channel').
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Réponses aux blocs de formulaire (votes de sondage / signatures de pétition).
     */
    public function blockResponses(): HasMany
    {
        return $this->hasMany(StudioBlockResponse::class);
    }

    /**
     * Toutes les versions publiées de ce contenu (v1, v2, …).
     */
    public function versions(): HasMany
    {
        return $this->hasMany(StudioContentVersion::class);
    }

    /**
     * La version actuellement en ligne — ce que voient les visiteurs.
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(StudioContentVersion::class, 'published_version_id');
    }

    /**
     * Dossiers éditoriaux dans lesquels ce contenu est rangé (placement vivant,
     * indépendant du versioning).
     */
    public function dossiers(): BelongsToMany
    {
        return $this->belongsToMany(Dossier::class, 'dossier_studio_content')->withTimestamps();
    }

    /**
     * Le contenu a-t-il au moins une version publiée en ligne ?
     */
    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_version_id !== null;
    }

    /**
     * Surcharge les attributs éditoriaux du modèle avec ceux de la version publiée
     * (titre, description, couverture, catégories, pages/sections/blocs). Sert de
     * point de passage unique pour toutes les lectures publiques : `format()`,
     * `StudioContentListing`, `StudioContentBlocks`, `DatasetController::queryPublic`…
     * voient alors la version en ligne sans changement de signature.
     *
     * Ne jamais `save()` un modèle après cet appel — `syncOriginal()` masque
     * volontairement la surcharge pour `isDirty()`.
     */
    public function applyPublishedPayload(): static
    {
        $version = $this->relationLoaded('publishedVersion')
            ? $this->publishedVersion
            : $this->publishedVersion()->first();

        if ($version === null) {
            return $this;
        }

        $this->forceFill([
            'title' => $version->title,
            'description' => $version->description,
            'coverage' => $version->coverage,
            'categories' => $version->categories ?? [],
            'pages' => $version->pages ?? [],
            'sections' => $version->sections ?? [],
            'blocks' => $version->blocks ?? [],
        ]);
        $this->syncOriginal();

        return $this;
    }
}
