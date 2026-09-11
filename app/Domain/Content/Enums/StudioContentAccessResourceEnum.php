<?php

namespace App\Domain\Content\Enums;

/**
 * Ressources partageables d'un contenu Studio (onglets dashboard + Studio).
 * Accès & partage, suppression et duplication restent réservés au propriétaire.
 */
enum StudioContentAccessResourceEnum: string
{
    case Contenu = 'contenu';
    case Publication = 'publication';
    case Sources = 'sources';
    case Historique = 'historique';
    case Studio = 'studio';

    public function label(): string
    {
        return match ($this) {
            self::Contenu => 'Contenu',
            self::Publication => 'Publication',
            self::Sources => 'Sources de données',
            self::Historique => 'Historique',
            self::Studio => 'Studio',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Contenu => 'Métadonnées et propriétés du contenu',
            self::Publication => 'Publication, planification et réglages publics',
            self::Sources => 'Jeux de données liés au contenu',
            self::Historique => 'Versions publiées et restauration',
            self::Studio => 'Éditeur de blocs',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }

    /**
     * Catalogue pour GET /studio/content/access-permissions.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $r) => [
            'key' => $r->value,
            'label' => $r->label(),
            'description' => $r->description(),
        ], self::cases());
    }
}
