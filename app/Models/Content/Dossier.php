<?php

namespace App\Models\Content;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Concerns\FiltersBySubBrand;
use App\Models\StudioContent;
use Database\Factories\DossierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * Dossier éditorial : conteneur nommé (titre, description, image) regroupant
 * plusieurs contenus autour d'un sujet suivi, rattaché à une ou plusieurs
 * catégories de contenu. Catalogue global géré en back-office Filament.
 */
class Dossier extends Model
{
    /** @use HasFactory<DossierFactory> */
    use FiltersBySubBrand, HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'cover_path',
        'keywords',
        'position',
        'is_active',
        'is_pinned',
        'icon',
        'sub_brand',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_active' => 'boolean',
            'is_pinned' => 'boolean',
            'position' => 'integer',
            'sub_brand' => SubBrandEnum::class,
        ];
    }

    protected static function newFactory(): DossierFactory
    {
        return DossierFactory::new();
    }

    public function contentCategories(): BelongsToMany
    {
        return $this->belongsToMany(ContentCategory::class, 'content_category_dossier');
    }

    public function studioContents(): BelongsToMany
    {
        return $this->belongsToMany(StudioContent::class, 'dossier_studio_content')->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopePinned(Builder $query): void
    {
        $query->where('is_pinned', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
