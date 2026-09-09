<?php

namespace App\Support;

use Filament\Forms\Components\Select;

/**
 * Liste blanche des icônes proposées pour une catégorie (contenu, chaîne, TV) en
 * back-office. Les clés sont des noms d'icônes Heroicons (jeu « outline ») que le
 * front sait rendre — voir `app/lib/category-icons.ts` côté statsio-front, qui
 * doit rester aligné sur cette liste.
 */
final class CategoryIcons
{
    /** @var array<string, string> nom Heroicons => libellé FR */
    public const OPTIONS = [
        'academic-cap' => 'Éducation',
        'banknotes' => 'Billets',
        'beaker' => 'Laboratoire',
        'bolt' => 'Énergie',
        'book-open' => 'Livre ouvert',
        'briefcase' => 'Travail',
        'building-library' => 'Institution',
        'building-office-2' => 'Entreprise',
        'building-storefront' => 'Commerce',
        'cake' => 'Gâteau',
        'calendar-days' => 'Calendrier',
        'camera' => 'Photo',
        'chart-bar' => 'Graphique en barres',
        'chart-pie' => 'Graphique circulaire',
        'chat-bubble-left-right' => 'Discussion',
        'cloud' => 'Météo / Climat',
        'cpu-chip' => 'Technologie',
        'currency-euro' => 'Économie',
        'film' => 'Cinéma',
        'fire' => 'Tendances',
        'flag' => 'Drapeau',
        'globe-alt' => 'Monde',
        'hand-raised' => 'Société',
        'heart' => 'Santé',
        'home-modern' => 'Logement',
        'light-bulb' => 'Idée / Sciences',
        'map' => 'Carte',
        'megaphone' => 'Médias',
        'microphone' => 'Micro',
        'musical-note' => 'Musique',
        'newspaper' => 'Actualité',
        'paint-brush' => 'Culture / Art',
        'rocket-launch' => 'Innovation',
        'scale' => 'Justice / Politique',
        'shield-check' => 'Sécurité',
        'sparkles' => 'People',
        'sun' => 'Environnement',
        'trophy' => 'Sport',
        'truck' => 'Transport',
        'tv' => 'Télévision',
        'users' => 'Communauté',
        'wrench-screwdriver' => 'Industrie',
    ];

    /**
     * Format attendu par un champ Select Filament.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::OPTIONS;
    }

    public static function isValid(?string $icon): bool
    {
        return $icon !== null && array_key_exists($icon, self::OPTIONS);
    }

    /**
     * Champ Filament réutilisable pour choisir l'icône d'une catégorie.
     */
    public static function formField(): Select
    {
        return Select::make('icon')
            ->label('Icône')
            ->options(self::options())
            ->searchable()
            ->native(false)
            ->placeholder('Aucune (pastille de couleur)')
            ->helperText('Affichée à gauche du nom de la catégorie sur le site public.');
    }
}
