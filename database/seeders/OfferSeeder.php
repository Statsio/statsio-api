<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\OfferComparisonRow;
use App\Models\PremiumBlockType;
use Illuminate\Database\Seeder;

/**
 * Offres Freemium / Premium et comparatif de la page publique /offres, tels que
 * définis par la maquette `Offres.dc.html`. Idempotent (réécrit par clé/libellé) —
 * les rédactions ajustent ensuite le contenu depuis le back-office Filament.
 */
class OfferSeeder extends Seeder
{
    public function run(): void
    {
        Offer::updateOrCreate(
            ['key' => 'freemium'],
            [
                'name' => 'Freemium',
                'tagline' => 'Pour démarrer et publier vos premiers contenus, seul ou en petite équipe.',
                'price_cents' => 0,
                'currency' => 'EUR',
                'period' => 'mois',
                'cta_label' => 'Continuer avec Freemium',
                'cta_url' => null,
                'badge_label' => null,
                'is_highlighted' => false,
                'is_active' => true,
                'position' => 1,
                'max_channels' => 1,
                'max_channel_members' => 3,
                'allows_identity_verification' => false,
                'features' => [
                    ['label' => 'Toutes les fonctionnalités du Studio, sauf le block Carte', 'included' => true],
                    ['label' => '1 chaîne éditoriale maximum', 'included' => false],
                    ['label' => "Jusqu'à 3 membres par chaîne", 'included' => false],
                    ['label' => "Sondages sans vérification d'identité des votants", 'included' => false],
                    ['label' => "Publication d'articles, StatsData et sondages", 'included' => true],
                    ['label' => 'Statistiques de base sur vos contenus', 'included' => true],
                ],
            ],
        );

        Offer::updateOrCreate(
            ['key' => 'premium'],
            [
                'name' => 'Premium',
                'tagline' => 'Sans aucune limite — pour les chaînes qui publient sérieusement et grandissent en équipe.',
                'price_cents' => 200,
                'currency' => 'EUR',
                'period' => 'mois',
                'cta_label' => 'Passer à Premium',
                'cta_url' => null,
                'badge_label' => 'RECOMMANDÉ',
                'is_highlighted' => true,
                'is_active' => true,
                'position' => 2,
                'max_channels' => null,
                'max_channel_members' => null,
                'allows_identity_verification' => true,
                'features' => [
                    ['label' => 'Toutes les fonctionnalités du Studio, y compris le block Carte', 'included' => true],
                    ['label' => 'Chaînes éditoriales illimitées', 'included' => true],
                    ['label' => 'Membres illimités par chaîne', 'included' => true],
                    ['label' => "Vérification d'identité activable sur vos sondages", 'included' => true],
                    ['label' => "Publication d'articles, StatsData et sondages", 'included' => true],
                    ['label' => 'Statistiques avancées sur vos contenus', 'included' => true],
                ],
            ],
        );

        $rows = [
            ['label' => 'Chaînes éditoriales', 'hint' => null, 'free_value' => '1 max', 'premium_value' => 'Illimitées'],
            ['label' => 'Membres par chaîne', 'hint' => null, 'free_value' => '3 max', 'premium_value' => 'Illimités'],
            ['label' => 'Blocks du Studio', 'hint' => 'Dont le block Carte', 'free_value' => 'Sauf Carte', 'premium_value' => 'Tous les blocks'],
            ['label' => "Vérification d'identité des sondages", 'hint' => 'Pour authentifier les votants', 'free_value' => 'Indisponible', 'premium_value' => 'Activable'],
            ['label' => 'Articles, StatsData, sondages', 'hint' => null, 'free_value' => 'Illimités', 'premium_value' => 'Illimités'],
            ['label' => 'Tarif', 'hint' => null, 'free_value' => '0 €/mois', 'premium_value' => '2 €/mois'],
        ];

        foreach ($rows as $position => $row) {
            OfferComparisonRow::updateOrCreate(
                ['label' => $row['label']],
                [...$row, 'position' => $position],
            );
        }

        // Le block « Carte » est le seul bloc réservé au Premium au lancement de l'offre.
        PremiumBlockType::firstOrCreate(['block_type' => 'map']);
    }
}
