<?php

namespace Database\Seeders;

use App\Models\Content\ContentCategory;
use App\Models\Content\Dossier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dossiers éditoriaux de départ. Idempotent (réécrit par slug à chaque exécution).
 * Les rédactions étoffent ensuite le catalogue depuis le back-office Filament.
 */
class DossierSeeder extends Seeder
{
    public function run(): void
    {
        $dossiers = [
            [
                'name' => 'Guerre en Ukraine',
                'categories' => ['monde'],
                'keywords' => ['ukraine', 'russie', 'poutine', 'zelensky', 'kiev', 'donbass', 'otan', 'offensive'],
                'is_pinned' => true,
            ],
            [
                'name' => 'Élection présidentielle',
                'categories' => ['politique'],
                'keywords' => ['présidentielle', 'candidat', 'campagne', 'élysée', 'scrutin', 'sondage'],
                'is_pinned' => true,
            ],
            [
                'name' => 'Crise climatique',
                'categories' => ['environnement', 'sciences'],
                'keywords' => ['climat', 'réchauffement', 'carbone', 'canicule', 'sécheresse', 'GIEC'],
            ],
            [
                'name' => 'Intelligence artificielle',
                'categories' => ['technologie', 'economie'],
                'keywords' => ['IA', 'ChatGPT', 'OpenAI', 'algorithme', 'modèle', 'automatisation'],
            ],
            [
                'name' => "Pouvoir d'achat",
                'categories' => ['economie', 'societe'],
                'keywords' => ['inflation', 'prix', 'salaire', 'énergie', 'carburant', 'facture'],
            ],
        ];

        foreach ($dossiers as $position => $entry) {
            $dossier = Dossier::updateOrCreate(
                ['slug' => Str::slug($entry['name'])],
                [
                    'name' => $entry['name'],
                    'keywords' => $entry['keywords'],
                    'position' => $position,
                    'is_active' => true,
                    'is_pinned' => $entry['is_pinned'] ?? false,
                ],
            );

            $categoryIds = ContentCategory::whereIn('slug', $entry['categories'])->pluck('id');
            $dossier->contentCategories()->sync($categoryIds);
        }
    }
}
