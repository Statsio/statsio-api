<?php

namespace App\Http\Controllers;

use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Domain\Content\Actions\GlobalSearchAction;
use App\Domain\Content\Actions\ListPublicStudioCatalogAction;
use App\Domain\Content\Actions\StudioContentDataSourcesAction;
use App\Domain\Content\Enums\SurveyKindEnum;
use App\Domain\Content\Support\ContentDatasetSources;
use App\Domain\User\Actions\RecordContentViewAction;
use App\Models\Channel\ChannelUser;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudioContentController extends Controller
{
    private const PUBLIC_CACHE_TTL = 300; // 5 minutes

    /**
     * Types de blocs qu'un article peut réutiliser via un bloc `sd-embed`
     * (« Bloc Statsdata »). Miroir de EMBEDDABLE_BLOCK_TYPES côté front.
     *
     * @var list<string>
     */
    public const EMBEDDABLE_BLOCK_TYPES = ['bar', 'line', 'pie', 'kpi', 'table', 'search'];

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $channelId = $request->query('channel_id');

        if ($channelId) {
            $isTeamMember = ChannelUser::where('channel_id', $channelId)
                ->where('user_id', $request->user()->id)
                ->whereIn('role', array_map(
                    fn (ChannelUserRoleEnum $role) => $role->value,
                    ChannelUserRoleEnum::getManagementRoles(),
                ))
                ->exists();

            if (! $isTeamMember) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }

            $query = StudioContent::with('channel.profile')
                ->where('channel_id', $channelId)
                ->where('published_as', 'channel');
        } else {
            $query = StudioContent::with('channel.profile')
                ->where('user_id', $request->user()->id);
        }

        $contents = $query
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contents->map(fn ($c) => $this->format($c)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|string|in:statsdata,article,survey',
            'survey_kind' => ['nullable', 'string', Rule::enum(SurveyKindEnum::class)],
            'requires_identity_verification' => 'nullable|boolean',
            'petition_goal' => 'nullable|integer|min:1',
            'petition_target' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
            'status' => 'nullable|string|in:draft,published',
            'sections' => 'nullable|array',
            'blocks' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:50',
            'emoji' => 'nullable|string|max:16',
            'coverage_type' => 'nullable|string|in:monde,pays,ville',
            'coverage_data' => 'nullable|array',
            'visibility' => 'nullable|string|in:public,protege,private',
            'published_as' => 'nullable|string|in:user,channel',
            'channel_id' => 'nullable|integer|exists:channels,id',
            'response_deadline' => 'nullable|date',
        ]);

        $type = $data['type'] ?? 'statsdata';
        $isSurvey = $type === 'survey';

        $content = StudioContent::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'type' => $type,
            'survey_kind' => $isSurvey ? ($data['survey_kind'] ?? SurveyKindEnum::SingleQuestion->value) : null,
            'requires_identity_verification' => $isSurvey ? (bool) ($data['requires_identity_verification'] ?? false) : false,
            'petition_goal' => $isSurvey ? ($data['petition_goal'] ?? null) : null,
            'petition_target' => $isSurvey ? ($data['petition_target'] ?? null) : null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'slug' => $this->generateUniqueSlug($data['title']),
            'sections' => $data['sections'] ?? [],
            'blocks' => $data['blocks'] ?? [],
            'categories' => $data['categories'] ?? [],
            'emoji' => $data['emoji'] ?? null,
            'coverage_type' => $data['coverage_type'] ?? null,
            'coverage_data' => $data['coverage_data'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'published_as' => $data['published_as'] ?? null,
            'channel_id' => $data['channel_id'] ?? null,
            'response_deadline' => $data['response_deadline'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->format($content),
        ], 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);

        return response()->json([
            'success' => true,
            'data' => $this->format($content),
        ]);
    }

    /**
     * Jeux de données rattachés à ce contenu (blocs référençant un `datasetId`),
     * avec leur fraîcheur. Alimente l'onglet « Sources de données » du dashboard
     * du contenu.
     */
    public function dataSources(Request $request, StudioContentDataSourcesAction $action, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);

        return response()->json([
            'success' => true,
            'data' => $action->getDataSources($content),
        ]);
    }

    public function catalogPublic(Request $request, ListPublicStudioCatalogAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$action->execute($request),
        ]);
    }

    public function searchPublic(Request $request, GlobalSearchAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            ...$action->execute((string) $request->query('q', ''), $request->user('sanctum')),
        ]);
    }

    public function indexPublic(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $channelId = $request->query('channel_id') ? (int) $request->query('channel_id') : null;
        $categories = $this->sanitizePublicCategories($request->query('categories'));

        $cacheKey = 'studio.public.index'.($type ? ".{$type}" : '').($channelId ? ".ch{$channelId}" : '').($categories ? '.'.implode(',', $categories) : '');

        $data = Cache::remember($cacheKey, self::PUBLIC_CACHE_TTL, function () use ($type, $channelId, $categories) {
            $contents = StudioContent::with(['user.profile', 'channel.profile'])
                ->where('status', 'published')
                ->when($type, fn ($q) => $q->where('type', $type))
                ->when($channelId, fn ($q) => $q->where('channel_id', $channelId)->where('published_as', 'channel'))
                ->when($categories, fn ($q) => $q->where(function ($sub) use ($categories) {
                    foreach ($categories as $category) {
                        $sub->orWhereJsonContains('categories', $category);
                    }
                }))
                ->orderBy('updated_at', 'desc')
                ->get();

            return $contents->map(fn ($c) => $this->format($c))->values()->all();
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Public, unauthenticated filter input — cap size and restrict charset so it can't be used
     * to spray the cache with arbitrary keys.
     */
    private function sanitizePublicCategories(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $categories = collect($raw)
            ->filter(fn ($c) => is_string($c) && preg_match('/^[a-z0-9_-]{1,50}$/', $c))
            ->unique()
            ->sort()
            ->values()
            ->take(5)
            ->all();

        return $categories;
    }

    /**
     * Recherche de contenus publiés pour la mention `@` de l'assistant du Studio :
     * articles, statsdata et sondages confondus. Renvoie des lignes légères
     * (sans blocs), triées par pertinence puis fraîcheur.
     */
    public function mentionsPublic(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $type = $request->query('type');
        $like = '%'.addcslashes(mb_strtolower($q), '%_\\').'%';

        $rows = StudioContent::with(['user.profile', 'channel.profile'])
            ->where('status', 'published')
            ->when(in_array($type, ['article', 'statsdata', 'survey'], true), fn ($query) => $query->where('type', $type))
            ->whereRaw('LOWER(title) LIKE ?', [$like])
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get()
            ->map(function (StudioContent $c) {
                $isChannel = $c->published_as === 'channel' && $c->channel;
                if ($isChannel) {
                    $name = $c->channel->profile?->name ?: 'Anonyme';
                } else {
                    $name = trim(($c->user?->profile?->first_name ?? '').' '.($c->user?->profile?->last_name ?? ''));
                    $name = $name !== '' ? $name : 'Anonyme';
                }

                return [
                    'id' => (string) $c->id,
                    'type' => $c->type ?? 'statsdata',
                    'slug' => $c->slug,
                    'title' => $c->title,
                    'publisher' => ['name' => $name, 'is_channel' => (bool) $isChannel],
                ];
            });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function showPublic(Request $request, string $slug): JsonResponse
    {
        // The published content itself is cached (safe to share across visitors), but "can this
        // viewer edit it" depends on who's asking — it's computed fresh on every request instead
        // of being baked into the cached payload.
        $content = Cache::remember("studio.public.show.{$slug}", self::PUBLIC_CACHE_TTL, function () use ($slug) {
            return StudioContent::with(['user.profile', 'channel.profile'])
                ->where('status', 'published')
                ->where(function ($q) use ($slug) {
                    $q->where('slug', $slug);
                    if (is_numeric($slug)) {
                        $q->orWhere('id', (int) $slug);
                    }
                })
                ->firstOrFail();
        });

        // Increment outside the cache closure so every real visitor request counts,
        // not just cache misses. Called on the instance (not a static query) so the
        // in-memory attribute used by format() below reflects this visit too.
        $content->increment('views_count');

        // Historique de consultation : trace la visite pour l'utilisateur connecté
        // (pas de progression ici — elle est poussée séparément par le front).
        $viewer = $request->user('sanctum');
        if ($viewer) {
            app(RecordContentViewAction::class)->execute($viewer, $content);
        }

        $data = $this->format($content);
        $data['can_edit'] = $this->canEditContent($viewer, $content);
        $data['is_favorited'] = $viewer
            ? $viewer->favorites()
                ->where('favoritable_type', $content->getMorphClass())
                ->where('favoritable_id', $content->getKey())
                ->exists()
            : false;

        // Suivi de la chaîne éditrice (bouton « Suivre » du bandeau publisher).
        if (is_array($data['channel'] ?? null)) {
            $data['channel']['is_following'] = $viewer && $content->channel_id
                ? $viewer->subscribedChannels()->where('channels.id', $content->channel_id)->exists()
                : false;
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Liste les blocs « embarquables » (graphique / KPI / tableau / recherche) d'un
     * Statsdata publié — étape 2 du sélecteur du bloc `sd-embed` dans le Studio.
     * Ne compte pas de vue.
     */
    public function listPublicBlocks(Request $request, string $slug): JsonResponse
    {
        $content = $this->findEmbeddableSourceBySlug($request, $slug);

        $blocks = collect($this->orderedBlocks($content))
            ->filter(fn ($b) => in_array($b['type'] ?? null, self::EMBEDDABLE_BLOCK_TYPES, true))
            ->map(fn ($b) => [
                'id' => (string) ($b['id'] ?? ''),
                'type' => $b['type'],
                'title' => $this->blockTitle($b),
                'datasetName' => $this->datasetNameFor($content, $b['datasetId'] ?? null),
            ])
            ->filter(fn ($b) => $b['id'] !== '')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'doc' => $this->slimDoc($content),
                'blocks' => $blocks,
            ],
        ]);
    }

    /**
     * Résout un bloc unique d'un Statsdata publié pour l'afficher dans un article
     * (bloc `sd-embed`). Renvoie la config du bloc verbatim + les métadonnées du
     * document source + la fraîcheur de ses datasets. Ne compte pas de vue.
     */
    public function showPublicBlock(Request $request, string $slug, string $blockId): JsonResponse
    {
        $content = $this->findEmbeddableSourceBySlug($request, $slug);

        $block = collect($content->blocks ?? [])
            ->first(fn ($b) => is_array($b) && ($b['id'] ?? null) === $blockId);

        if (! $block || ! in_array($block['type'] ?? null, self::EMBEDDABLE_BLOCK_TYPES, true)) {
            return response()->json(['success' => false, 'message' => 'Bloc introuvable.'], 404);
        }

        $blockDatasetIds = $this->datasetIdsFor($block);
        $datasets = collect(self::format($content)['datasets'])
            ->filter(fn ($d) => in_array((string) $d['id'], $blockDatasetIds, true))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'block' => $block,
                'doc' => $this->slimDoc($content),
                'pages' => $content->pages ?? [],
                'datasets' => $datasets,
                // Paramètres déclarés sur la page du bloc — l'article s'en sert pour
                // résoudre les jetons `{{param}}` des filtres/expressions du bloc
                // (défaut de la page source, ou valeur choisie par l'auteur).
                'params' => $this->paramsForBlockPage($content, $block),
            ],
        ]);
    }

    /**
     * Paramètres (`PageParam[]`) déclarés sur la page à laquelle appartient un bloc.
     *
     * @return list<array<string,mixed>>
     */
    private function paramsForBlockPage(StudioContent $content, array $block): array
    {
        $zone = (string) ($block['zoneId'] ?? '');
        $sectionId = str_contains($zone, '-') ? substr($zone, 0, (int) strrpos($zone, '-')) : $zone;

        $pageId = 'default';
        foreach ($content->sections ?? [] as $section) {
            if (($section['id'] ?? null) === $sectionId) {
                $pageId = (string) ($section['pageId'] ?? 'default');
                break;
            }
        }

        foreach ($content->pages ?? [] as $page) {
            if (($page['id'] ?? null) === $pageId) {
                return array_values(array_filter($page['params'] ?? [], 'is_array'));
            }
        }

        // Repli : une seule page → ses params.
        $pages = $content->pages ?? [];
        if (count($pages) === 1 && is_array($pages[0]['params'] ?? null)) {
            return array_values(array_filter($pages[0]['params'], 'is_array'));
        }

        return [];
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'survey_kind' => ['sometimes', 'nullable', 'string', Rule::enum(SurveyKindEnum::class)],
            'requires_identity_verification' => 'sometimes|boolean',
            'petition_goal' => 'sometimes|nullable|integer|min:1',
            'petition_target' => 'sometimes|nullable|string|max:2000',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                // $content->id est la clé primaire du modèle résolu par findBySlug(), pas une entrée
                // utilisateur — usage canonique de Rule::unique()->ignore().
                // nosemgrep: php.laravel.security.laravel-unsafe-validator.laravel-unsafe-validator
                Rule::unique('studio_contents', 'slug')->ignore($content->id),
            ],
            'description' => 'sometimes|nullable|string|max:2000',
            'status' => 'sometimes|string|in:draft,published',
            'pages' => 'sometimes|nullable|array',
            'sections' => 'sometimes|nullable|array',
            'blocks' => 'sometimes|nullable|array',
            'categories' => 'sometimes|nullable|array',
            'categories.*' => 'string|max:50',
            'emoji' => 'sometimes|nullable|string|max:16',
            'coverage_type' => 'sometimes|nullable|string|in:monde,pays,ville',
            'coverage_data' => 'sometimes|nullable|array',
            'visibility' => 'sometimes|string|in:public,protege,private',
            'published_as' => 'sometimes|nullable|string|in:user,channel',
            'channel_id' => 'sometimes|nullable|integer|exists:channels,id',
            'response_deadline' => 'sometimes|nullable|date',
            'thumbnail' => 'sometimes|file|image|max:5120',
            'remove_thumbnail' => 'sometimes|boolean',
        ]);

        $thumbnailFile = $request->file('thumbnail');
        $removeThumbnail = $request->boolean('remove_thumbnail');
        unset($data['thumbnail'], $data['remove_thumbnail']);

        // Purge le cache public de l'ancien slug avant qu'il ne change.
        $previousSlug = $content->slug;

        $content->update($data);

        if ($previousSlug !== $content->slug) {
            Cache::forget("studio.public.show.{$previousSlug}");
        }

        if ($thumbnailFile) {
            $content->getMedia('thumbnail')->each(fn ($m) => $content->deleteMedia($m));
            $content->addMedia($thumbnailFile, 'studio-content-thumbnails', 'thumbnail');
        } elseif ($removeThumbnail) {
            $content->getMedia('thumbnail')->each(fn ($m) => $content->deleteMedia($m));
        }

        $this->forgetPublicCache($content);

        return response()->json([
            'success' => true,
            'data' => $this->format($content->fresh()),
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $content = $this->findBySlug($request->user()->id, $slug);
        $content->clearMedia();
        $content->delete();
        $this->forgetPublicCache($content);

        return response()->json(['success' => true, 'message' => 'Contenu supprimé.']);
    }

    private function forgetPublicCache(StudioContent $content): void
    {
        Cache::forget('studio.public.index');
        Cache::forget("studio.public.index.{$content->type}");
        Cache::forget("studio.public.show.{$content->slug}");
        Cache::forget("studio.public.show.{$content->id}");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function canEditContent(?User $user, StudioContent $content): bool
    {
        return $user !== null && $user->can('update', $content);
    }

    /**
     * Source d'un bloc `sd-embed` : le Statsdata doit être publié, OU en brouillon
     * mais éditable par l'appelant authentifié (aperçu de son propre contenu dans le
     * Studio). Sans cache ni compteur de vue.
     */
    private function findEmbeddableSourceBySlug(Request $request, string $slug): StudioContent
    {
        $content = StudioContent::with(['user.profile', 'channel.profile'])
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        if ($content->status !== 'published') {
            $viewer = $request->user('sanctum');
            abort_unless($viewer !== null && $viewer->can('update', $content), 404);
        }

        return $content;
    }

    /** Métadonnées légères d'un document source (auteur / chaîne) pour un bloc embarqué. */
    private function slimDoc(StudioContent $content): array
    {
        $full = self::format($content);

        return [
            'id' => $full['id'],
            'slug' => $full['slug'],
            'title' => $full['title'],
            'type' => $full['type'],
            'status' => $full['status'],
            'published_as' => $full['published_as'],
            'channel' => $full['channel'],
            'author' => $full['author'],
        ];
    }

    /**
     * Ids de datasets référencés par un bloc (dataset principal + jointures).
     *
     * @return list<string>
     */
    private function datasetIdsFor(array $block): array
    {
        $ids = ContentDatasetSources::blockDatasetIds($block);
        foreach ($block['fieldMapping']['searchSources'] ?? [] as $source) {
            $ids[] = $source['datasetId'] ?? null;
        }
        foreach ($block['fieldMapping']['searchJoins'] ?? [] as $join) {
            $ids[] = $join['datasetId'] ?? null;
        }

        return array_values(array_unique(array_map('strval', array_filter($ids))));
    }

    private function blockTitle(array $block): string
    {
        $config = is_array($block['config'] ?? null) ? $block['config'] : [];
        $title = trim((string) ($config['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return match ($block['type'] ?? '') {
            'bar' => 'Graphique en barres',
            'line' => 'Graphique en lignes',
            'pie' => 'Camembert',
            'kpi' => 'Indicateur',
            'table' => 'Tableau',
            'search' => 'Recherche',
            default => 'Bloc',
        };
    }

    private function datasetNameFor(StudioContent $content, ?string $datasetId): ?string
    {
        if (! $datasetId) {
            return null;
        }
        foreach (self::format($content)['datasets'] as $d) {
            if ((string) $d['id'] === (string) $datasetId) {
                return $d['name'];
            }
        }

        return null;
    }

    /**
     * Blocs du contenu dans l'ordre de lecture : parcours des sections (ordre du
     * tableau `sections`) → colonnes → blocs de la zone `"{sectionId}-{col}"`.
     * Repli : ordre brut du tableau `blocks`.
     *
     * @return list<array<string,mixed>>
     */
    private function orderedBlocks(StudioContent $content): array
    {
        $blocks = array_values(array_filter($content->blocks ?? [], 'is_array'));
        $sections = array_values(array_filter($content->sections ?? [], 'is_array'));
        if (empty($sections)) {
            return $blocks;
        }

        $byZone = [];
        foreach ($blocks as $block) {
            $byZone[$block['zoneId'] ?? ''][] = $block;
        }

        $ordered = [];
        $seen = [];
        foreach ($sections as $section) {
            $sectionId = $section['id'] ?? '';
            for ($col = 0; $col < 4; $col++) {
                foreach ($byZone["{$sectionId}-{$col}"] ?? [] as $block) {
                    $ordered[] = $block;
                    $seen[$block['id'] ?? ''] = true;
                }
            }
        }
        // Blocs orphelins (zone inconnue, enfants de script…) : à la fin, ordre brut.
        foreach ($blocks as $block) {
            if (! isset($seen[$block['id'] ?? ''])) {
                $ordered[] = $block;
            }
        }

        return $ordered;
    }

    private function findBySlug(int $userId, string $slug): StudioContent
    {
        return StudioContent::with('channel.profile')
            ->where('user_id', $userId)
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'statsdata';
        $slug = $base;
        $i = 2;
        while (StudioContent::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public static function format(StudioContent $content): array
    {
        if ($content->published_as === 'channel' && $content->channel) {
            $authorName = $content->channel->profile?->name ?: 'Anonyme';
        } else {
            $profile = $content->user?->profile;
            $authorName = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''));
            $authorName = $authorName ?: 'Anonyme';
        }

        $blocks = $content->blocks ?? [];
        $datasetIds = [];
        foreach ($blocks as $b) {
            if (is_array($b)) {
                array_push($datasetIds, ...ContentDatasetSources::blockDatasetIds($b));
            }
        }
        $datasetIds = array_values(array_unique($datasetIds));

        $datasets = [];
        if (! empty($datasetIds)) {
            // Inclut les sources publiques rattachées au propriétaire via le pivot
            // data_source_user (l'assistant IA peut lier un bloc à une telle source).
            $datasets = Dataset::whereIn('id', $datasetIds)
                ->with('dataSource')
                ->where(fn ($q) => $q
                    ->where('user_id', $content->user_id)
                    ->orWhereHas('dataSource.users', fn ($u) => $u->where('user_id', $content->user_id)))
                ->get(['id', 'name', 'row_count', 'data_source_id'])
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'row_count' => $d->row_count,
                    // Fraîcheur : alimente le badge « Mis à jour il y a… » de la page publique.
                    'is_live' => (bool) $d->dataSource?->isLive(),
                    'last_refreshed_at' => $d->dataSource?->last_refreshed_at?->toIso8601String(),
                    'next_refresh_at' => $d->dataSource?->next_refresh_at?->toIso8601String(),
                    'refresh_frequency' => $d->dataSource?->refresh_frequency?->value,
                ])
                ->toArray();
        }

        return [
            'id' => (string) $content->id,
            'title' => $content->title,
            'type' => $content->type ?? 'statsdata',
            'survey_kind' => $content->survey_kind,
            'requires_identity_verification' => (bool) $content->requires_identity_verification,
            'petition_goal' => $content->petition_goal,
            'petition_target' => $content->petition_target,
            'description' => $content->description,
            'status' => $content->status ?? 'draft',
            'views_count' => $content->views_count ?? 0,
            'visibility' => $content->visibility ?? 'private',
            'thumbnail_url' => $content->getFirstMediaUrl('thumbnail'),
            'slug' => $content->slug,
            'categories' => $content->categories ?? [],
            'emoji' => $content->emoji,
            'coverage_type' => $content->coverage_type,
            'coverage_data' => $content->coverage_data ?? [],
            'response_deadline' => $content->response_deadline?->toIso8601String(),
            'published_as' => $content->published_as,
            'channel_id' => $content->channel_id,
            'channel' => $content->published_as === 'channel' && $content->channel
                ? [
                    'id' => $content->channel->id,
                    'name' => $content->channel->profile?->name,
                    'handle' => $content->channel->profile?->handle,
                    'logo_url' => $content->channel->profile?->logo_url,
                    'custom_color_primary' => $content->channel->profile?->custom_color_primary,
                    'custom_color_secondary' => $content->channel->profile?->custom_color_secondary,
                ]
                : null,
            'author' => ['name' => $authorName],
            'datasets' => $datasets,
            'pages' => $content->pages ?? [],
            'sections' => $content->sections ?? [],
            'blocks' => $content->blocks ?? [],
            'created_at' => $content->created_at->toIso8601String(),
            'updated_at' => $content->updated_at->toIso8601String(),
        ];
    }
}
