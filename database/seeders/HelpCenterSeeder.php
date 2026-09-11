<?php

namespace Database\Seeders;

use App\Models\Help\HelpArticle;
use App\Models\Help\HelpCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contenu de départ du centre d'aide (catégories + articles), repris de la
 * maquette « Centre Aide ». N'agit que si aucune catégorie n'existe encore :
 * ne touche jamais à du contenu déjà créé/édité en back-office.
 */
class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        if (HelpCategory::query()->exists()) {
            $this->command?->info('Centre d\'aide déjà peuplé, seeder ignoré.');

            return;
        }

        foreach (self::CATEGORIES as $categoryPosition => $categoryData) {
            $category = HelpCategory::create([
                'slug' => Str::slug($categoryData['name']),
                'name' => $categoryData['name'],
                'icon' => $categoryData['icon'],
                'color' => $categoryData['color'],
                'description' => $categoryData['description'],
                'position' => $categoryPosition,
                'is_active' => true,
            ]);

            foreach ($categoryData['articles'] as $articlePosition => $articleData) {
                HelpArticle::create([
                    'help_category_id' => $category->id,
                    'slug' => Str::slug($articleData['title']),
                    'title' => $articleData['title'],
                    'excerpt' => $articleData['excerpt'],
                    'content_html' => collect($articleData['paragraphs'])
                        ->map(fn (string $p) => '<p>'.e($p).'</p>')
                        ->implode(''),
                    'position' => $articlePosition,
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @var list<array{name: string, icon: string, color: string, description: string, articles: list<array{title: string, excerpt: string, paragraphs: list<string>}>}>
     */
    private const CATEGORIES = [
        [
            'name' => 'Premiers pas',
            'icon' => 'rocket-launch',
            'color' => '#8b5cf6',
            'description' => "Créer un compte, découvrir l'interface et publier votre premier contenu.",
            'articles' => [
                [
                    'title' => 'Créer un compte Statsio',
                    'excerpt' => "Les étapes pour s'inscrire et configurer votre profil.",
                    'paragraphs' => [
                        "Rendez-vous sur la page d'inscription et renseignez votre adresse e-mail professionnelle ou personnelle. Un lien de confirmation vous est envoyé immédiatement.",
                        'Une fois votre compte validé, choisissez si vous souhaitez avant tout lire des contenus ou également en publier — cela personnalise votre tableau de bord.',
                        'Vous pouvez à tout moment compléter votre profil : photo, bio et liens vers vos réseaux, depuis la page Mon compte.',
                    ],
                ],
                [
                    'title' => "Découvrir l'interface Statsio",
                    'excerpt' => 'Un tour du tableau de bord et des menus principaux.',
                    'paragraphs' => [
                        "La barre de navigation en haut de page donne accès aux Articles, StatsData, Sondages et Chaînes depuis n'importe quel écran.",
                        "Votre menu de profil, en haut à droite, regroupe les paramètres de compte, l'accès à votre chaîne et la déconnexion.",
                        "Le centre d'aide reste accessible en un clic depuis le pied de page à tout moment.",
                    ],
                ],
                [
                    'title' => 'Publier votre premier contenu',
                    'excerpt' => 'Choisir entre article, StatsData ou sondage.',
                    'paragraphs' => [
                        'Depuis votre chaîne, le bouton Nouveau contenu vous propose trois formats : article, StatsData ou sondage.',
                        "Un article combine texte et blocs interactifs ; une StatsData part d'un jeu de données ; un sondage se crée en définissant une question et ses réponses.",
                        'Chaque contenu peut être enregistré en brouillon avant publication définitive.',
                    ],
                ],
            ],
        ],
        [
            'name' => 'StatsData',
            'icon' => 'chart-bar',
            'color' => '#3b82f6',
            'description' => 'Importer un CSV, connecter une API, configurer vos visualisations.',
            'articles' => [
                [
                    'title' => 'Importer un fichier CSV dans une StatsData',
                    'excerpt' => 'Formats acceptés et bonnes pratiques.',
                    'paragraphs' => [
                        "Statsio accepte les fichiers CSV et Excel jusqu'à 50 Mo. La première ligne doit contenir les en-têtes de colonnes.",
                        "Après l'import, un assistant détecte automatiquement les types de données (nombre, date, texte) que vous pouvez ajuster manuellement.",
                        'Les StatsData basées sur un fichier peuvent être remises à jour à tout moment en réimportant une nouvelle version.',
                    ],
                ],
                [
                    'title' => 'Connecter une API externe',
                    'excerpt' => 'Brancher une source de données en direct.',
                    'paragraphs' => [
                        "Dans les paramètres de votre StatsData, choisissez Source API et renseignez l'URL du point d'accès ainsi que la clé d'authentification si nécessaire.",
                        'Définissez la fréquence de rafraîchissement : toutes les heures, chaque jour, ou manuellement.',
                        'Statsio valide la structure des données reçues avant de les rendre disponibles dans vos visualisations.',
                    ],
                ],
                [
                    'title' => 'Configurer vos visualisations',
                    'excerpt' => 'Choisir le bon type de graphique.',
                    'paragraphs' => [
                        'Chaque StatsData propose plusieurs types de visualisation : courbes, barres, cartes ou tableaux filtrables.',
                        "Les axes, filtres par défaut et la palette de couleurs se configurent dans le panneau latéral de l'éditeur.",
                        "Vos lecteurs peuvent ensuite filtrer et explorer les données directement, sans modifier votre configuration d'origine.",
                    ],
                ],
            ],
        ],
        [
            'name' => 'Articles',
            'icon' => 'newspaper',
            'color' => '#e11d48',
            'description' => 'Rédiger, insérer des blocs interactifs et publier vos articles.',
            'articles' => [
                [
                    'title' => 'Ajouter un graphique interactif à un article',
                    'excerpt' => 'Relier un article à une StatsData existante.',
                    'paragraphs' => [
                        "Dans l'éditeur d'article, insérez un bloc StatsData depuis la barre d'outils puis sélectionnez la donnée à afficher parmi vos StatsData publiées.",
                        "Le graphique reste connecté à la source : toute mise à jour de la StatsData se répercute automatiquement dans l'article.",
                        'Vous pouvez restreindre les filtres visibles pour les lecteurs afin de garder le contexte de votre analyse.',
                    ],
                ],
                [
                    'title' => 'Comprendre les statuts de publication',
                    'excerpt' => 'Brouillon, en relecture, publié.',
                    'paragraphs' => [
                        'Un contenu passe par trois statuts : Brouillon, En relecture puis Publié.',
                        'Le statut En relecture permet de partager un lien de prévisualisation avant la mise en ligne définitive.',
                        'Un contenu publié peut être dépublié à tout moment sans perdre son historique de statistiques.',
                    ],
                ],
            ],
        ],
        [
            'name' => 'Sondages',
            'icon' => 'chat-bubble-left-right',
            'color' => '#166534',
            'description' => 'Créer un sondage, suivre les résultats en direct.',
            'articles' => [
                [
                    'title' => 'Créer un sondage',
                    'excerpt' => 'Définir une question et ses réponses.',
                    'paragraphs' => [
                        'Depuis votre chaîne, créez un nouveau sondage en renseignant une question et deux à cinq choix de réponse.',
                        "Vous pouvez limiter le sondage à un vote par lecteur connecté ou l'ouvrir aux visiteurs anonymes.",
                        'Le sondage peut être publié seul ou intégré dans un article existant.',
                    ],
                ],
                [
                    'title' => 'Publier un sondage avec résultats en direct',
                    'excerpt' => 'Suivre les votes en temps réel.',
                    'paragraphs' => [
                        "Les résultats d'un sondage se mettent à jour en direct pour tous les lecteurs, sans rechargement de page.",
                        'Un export CSV des votes est disponible à tout moment depuis le tableau de bord de votre chaîne.',
                        'Vous pouvez clôturer un sondage manuellement ou programmer une date de fin.',
                    ],
                ],
            ],
        ],
        [
            'name' => 'Chaînes & abonnements',
            'icon' => 'users',
            'color' => '#8b5cf6',
            'description' => 'Gérer votre chaîne, vos abonnés et votre monétisation.',
            'articles' => [
                [
                    'title' => 'Créer votre première chaîne en 5 minutes',
                    'excerpt' => 'Nom, thématique et premier contenu.',
                    'paragraphs' => [
                        'Choisissez un nom de chaîne, une thématique principale et une image de couverture pour vous lancer.',
                        "Votre chaîne dispose immédiatement d'une page publique où apparaissent tous vos contenus publiés.",
                        "Vous pouvez inviter d'autres créateurs à contribuer à votre chaîne depuis les paramètres d'équipe.",
                    ],
                ],
                [
                    'title' => 'Configurer les revenus de votre chaîne',
                    'excerpt' => 'Abonnements payants et paliers.',
                    'paragraphs' => [
                        "Activez un ou plusieurs paliers d'abonnement payant pour donner accès à des contenus exclusifs.",
                        'Statsio prélève une commission sur chaque abonnement ; le reversement est mensuel vers le compte bancaire renseigné.',
                        'Un tableau de bord dédié suit vos revenus, nouveaux abonnés et désabonnements.',
                    ],
                ],
            ],
        ],
        [
            'name' => 'Compte & facturation',
            'icon' => 'banknotes',
            'color' => '#3b82f6',
            'description' => "Paramètres, moyens de paiement et gestion de l'abonnement.",
            'articles' => [
                [
                    'title' => 'Modifier ou annuler votre abonnement',
                    'excerpt' => 'Changer de palier ou résilier.',
                    'paragraphs' => [
                        "Rendez-vous dans Mon compte puis Facturation pour changer de palier d'abonnement à tout moment.",
                        "Une résiliation prend effet à la fin de la période déjà payée ; vous conservez l'accès jusqu'à cette date.",
                        'Toutes vos factures sont téléchargeables au format PDF depuis cet écran.',
                    ],
                ],
            ],
        ],
    ];
}
