<?php

namespace App\Http\Controllers;

use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Domain\Content\Actions\GlobalSearchAction;
use App\Domain\Content\Actions\ListPublicStudioCatalogAction;
use App\Domain\Content\Actions\PublishStudioContentAction;
use App\Domain\Content\Actions\StudioContentDataSourcesAction;
use App\Domain\Content\Actions\SuggestDossiersAction;
use App\Domain\Content\Enums\ContentCoverageEnum;
use App\Domain\Content\Enums\StudioContentAccessLevelEnum;
use App\Domain\Content\Enums\SubBrandEnum;
use App\Domain\Content\Enums\SurveyKindEnum;
use App\Domain\Content\Exceptions\PremiumFeatureRequiredException;
use App\Domain\Content\Support\ContentDatasetSources;
use App\Domain\Content\Support\PremiumBlockGate;
use App\Domain\Content\Support\PremiumLimits;
use App\Domain\Content\Support\StudioContentAccess;
use App\Domain\Content\Support\StudioContentBlocks;
use App\Domain\User\Actions\RecordContentViewAction;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelUser;
use App\Models\DataIngestion\Dataset;
use App\Models\Studio\StudioContentVersion;
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

    private const PUBLIC_CACHE_TAG = 'studio-public';

    /**
     * Types de blocs réutilisables via `sd-embed` / iframe publique
     * (« Bloc Statsdata »). Miroir de EMBEDDABLE_BLOCK_TYPES côté front.
     *
     * @var list<string>
     */
    public const EMBEDDABLE_BLOCK_TYPES = ['bar', 'line', 'pie', 'kpi', 'table', 'search', 'map'];

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

            $query = StudioContent::with(['channel.profile', 'dossiers'])
                ->where('channel_id', $channelId)
                ->where('published_as', 'channel');
        } else {
            $userId = $request->user()->id;
            $query = StudioContent::with(['channel.profile', 'dossiers', 'collaborators'])
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->orWhereHas('collaborators', fn ($c) => $c->where('user_id', $userId));
                });
        }

        $contents = $query
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contents->map(function ($c) use ($request) {
                $row = $this->format($c);
                $row['access'] = StudioContentAccess::payload($request->user(), $c);
                $row['is_shared'] = ! StudioContentAccess::isOwner($request->user(), $c);

                return $row;
            }),
        ]);
    }

    public function store(Request $request, PremiumBlockGate $premiumGate): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|string|in:statsdata,article,survey',
            'survey_kind' => ['nullable', 'string', Rule::enum(SurveyKindEnum::class)],
            'requires_identity_verification' => 'nullable|boolean',
            'petition_goal' => 'nullable|integer|min:1',
            'petition_target' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:2000',
            'sections' => 'nullable|array',
            'blocks' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:50',
            'coverage' => ['nullable', Rule::enum(ContentCoverageEnum::class)],
            'sub_brand' => ['sometimes', Rule::in(SubBrandEnum::contentValues())],
            'response_deadline' => 'nullable|date',
        ]);

        $type = $data['type'] ?? 'statsdata';
        $isSurvey = $type === 'survey';

        try {
            // Pas de canal à la création (le champ n'existe pas encore ici) : gate basé
            // sur l'auteur seul, pas de bloc existant à préserver.
            $premiumGate->assertChange($request->user(), null, [], $data);
        } catch (PremiumFeatureRequiredException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'blocked_blocks' => $e->blockedBlockTypes(),
            ], 403);
        }

        if ($isSurvey
            && ($data['requires_identity_verification'] ?? false)
            && ! PremiumLimits::effectiveOffer($request->user(), null)?->allows_identity_verification) {
            return response()->json([
                'success' => false,
                'message' => __('errors.premium_identity_verification'),
            ], 403);
        }

        // Un contenu naît toujours en brouillon : la publication (et le choix
        // « en mon nom / au nom d'une chaîne ») se fait ensuite depuis le Studio.
        $content = StudioContent::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'type' => $type,
            'survey_kind' => $isSurvey ? ($data['survey_kind'] ?? SurveyKindEnum::SingleQuestion->value) : null,
            'requires_identity_verification' => $isSurvey ? (bool) ($data['requires_identity_verification'] ?? false) : false,
            'petition_goal' => $isSurvey ? ($data['petition_goal'] ?? null) : null,
            'petition_target' => $isSurvey ? ($data['petition_target'] ?? null) : null,
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'slug' => $this->generateUniqueSlug($data['title']),
            'sections' => $data['sections'] ?? [],
            'blocks' => $data['blocks'] ?? [],
            'categories' => $data['categories'] ?? [],
            'coverage' => $data['coverage'] ?? null,
            'sub_brand' => $data['sub_brand'] ?? 'statsio',
            'response_deadline' => $data['response_deadline'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->format($content),
        ], 201);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $content = $this->findAccessible($request->user(), $slug);
        $data = $this->format($content);
        $data['access'] = StudioContentAccess::payload($request->user(), $content);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Jeux de données rattachés à ce contenu (blocs référençant un `datasetId`),
     * avec leur fraîcheur. Alimente l'onglet « Sources de données » du dashboard
     * du contenu.
     */
    public function dataSources(Request $request, StudioContentDataSourcesAction $action, string $slug): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'sources',
            StudioContentAccessLevelEnum::Read,
        );

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
        $subBrand = SubBrandEnum::sanitize($request->query('sub_brand'));

        $cacheKey = 'studio.public.index'.($type ? ".{$type}" : '').($channelId ? ".ch{$channelId}" : '').($subBrand ? ".{$subBrand}" : '').($categories ? '.'.implode(',', $categories) : '');

        $data = $this->rememberPublic($cacheKey, function () use ($type, $channelId, $categories, $subBrand) {
            $contents = StudioContent::with(['user.profile', 'channel.profile', 'publishedVersion'])
                ->where('status', 'published')
                ->when($type, fn ($q) => $q->where('type', $type))
                ->when($channelId, fn ($q) => $q->where('channel_id', $channelId)->where('published_as', 'channel'))
                ->when($subBrand, fn ($q) => $q->where('sub_brand', $subBrand))
                ->when($categories, fn ($q) => $q->where(function ($sub) use ($categories) {
                    foreach ($categories as $category) {
                        $sub->orWhereJsonContains('categories', $category);
                    }
                }))
                ->orderBy('updated_at', 'desc')
                ->get();

            return $contents->map(fn ($c) => $this->format($c->applyPublishedPayload()))->values()->all();
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

        $rows = StudioContent::with(['user.profile', 'channel.profile', 'publishedVersion'])
            ->where('status', 'published')
            ->when(in_array($type, ['article', 'statsdata', 'survey'], true), fn ($query) => $query->where('type', $type))
            ->whereRaw('LOWER(title) LIKE ?', [$like])
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get()
            ->map(function (StudioContent $c) {
                $c->applyPublishedPayload();
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
        $content = $this->rememberPublic("studio.public.show.{$slug}", function () use ($slug) {
            return StudioContent::with(['user.profile', 'channel.profile', 'publishedVersion'])
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

        // La page publique affiche la dernière version PUBLIÉE, jamais le brouillon en cours.
        $content->applyPublishedPayload();

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

    public function update(Request $request, PremiumBlockGate $premiumGate, string $slug): JsonResponse
    {
        $content = $this->findAccessible($request->user(), $slug);

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
                // $content->id est la clé primaire du modèle résolu par findAccessible(), pas une entrée
                // utilisateur — usage canonique de Rule::unique()->ignore().
                // nosemgrep: php.laravel.security.laravel-unsafe-validator.laravel-unsafe-validator
                Rule::unique('studio_contents', 'slug')->ignore($content->id),
            ],
            'description' => 'sometimes|nullable|string|max:2000',
            'pages' => 'sometimes|nullable|array',
            'sections' => 'sometimes|nullable|array',
            'blocks' => 'sometimes|nullable|array',
            'categories' => 'sometimes|nullable|array',
            'categories.*' => 'string|max:50',
            'card_block_id' => 'sometimes|nullable|string|max:64',
            'coverage' => ['sometimes', 'nullable', Rule::enum(ContentCoverageEnum::class)],
            'sub_brand' => ['sometimes', Rule::in(SubBrandEnum::contentValues())],
            'published_as' => 'sometimes|nullable|string|in:user,channel',
            'channel_id' => 'sometimes|nullable|integer|exists:channels,id',
            'response_deadline' => 'sometimes|nullable|date',
            'scheduled_publish_at' => 'sometimes|nullable|date',
            'comments_enabled' => 'sometimes|boolean',
            'download_enabled' => 'sometimes|boolean',
            'embed_enabled' => 'sometimes|boolean',
            'thumbnail' => 'sometimes|file|image|max:5120',
            'thumbnail_media_id' => 'sometimes|nullable|integer|exists:media,id',
            'remove_thumbnail' => 'sometimes|boolean',
        ]);

        $this->assertUpdatePermissions($request->user(), $content, $data, $request);

        $thumbnailFile = $request->file('thumbnail');
        $thumbnailMediaId = $request->filled('thumbnail_media_id') ? (int) $request->input('thumbnail_media_id') : null;
        $removeThumbnail = $request->boolean('remove_thumbnail');
        unset($data['thumbnail'], $data['thumbnail_media_id'], $data['remove_thumbnail']);

        // Canal cible de ce contenu (déjà publié au nom d'une chaîne, ou en train de le
        // devenir dans cette requête) : sa chaîne hérite du Premium de son propriétaire.
        $targetChannelId = $data['channel_id'] ?? $content->channel_id;
        $targetPublishedAs = $data['published_as'] ?? $content->published_as;
        $targetChannel = ($targetPublishedAs === 'channel' && $targetChannelId)
            ? Channel::find($targetChannelId)
            : null;

        // Blocs premium : uniquement si le payload touche blocks/pages/sections. Diff avec
        // l'état persisté — un bloc premium déjà en place et inchangé n'est jamais bloquant
        // (grandfathering), seuls l'ajout ou la modification d'un bloc premium le sont.
        if (array_intersect(['blocks', 'pages', 'sections'], array_keys($data)) !== []) {
            try {
                $premiumGate->assertChange($request->user(), $targetChannel, StudioContentBlocks::all($content), $data);
            } catch (PremiumFeatureRequiredException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'blocked_blocks' => $e->blockedBlockTypes(),
                ], 403);
            }
        }

        // Gate uniquement la TRANSITION désactivé → activé — un sondage déjà configuré
        // avec vérification d'identité reste publiable/modifiable même si l'auteur (ou le
        // propriétaire de la chaîne) perd le Premium ensuite.
        $enablingIdentityVerification = ($data['requires_identity_verification'] ?? false)
            && ! $content->requires_identity_verification;

        if ($content->type === 'survey'
            && $enablingIdentityVerification
            && ! PremiumLimits::effectiveOffer($request->user(), $targetChannel)?->allows_identity_verification) {
            return response()->json([
                'success' => false,
                'message' => __('errors.premium_identity_verification'),
            ], 403);
        }

        // Retirer la date de programmation d'un contenu déjà programmé le ramène en brouillon.
        if (array_key_exists('scheduled_publish_at', $data)
            && $data['scheduled_publish_at'] === null
            && $content->status === 'scheduled') {
            $data['status'] = 'draft';
        }

        $content->update($data);

        if ($thumbnailFile) {
            $content->getMedia('thumbnail')->each(fn ($m) => $content->deleteMedia($m));
            $content->addMedia($thumbnailFile, 'studio-content-thumbnails', 'thumbnail');
        } elseif ($thumbnailMediaId !== null) {
            // La bibliothèque source doit appartenir au propriétaire du contenu.
            $content->attachMediaFromLibrary(
                $thumbnailMediaId,
                'studio-content-thumbnails',
                'thumbnail',
                (int) $content->user_id,
            );
        } elseif ($removeThumbnail) {
            $content->getMedia('thumbnail')->each(fn ($m) => $content->deleteMedia($m));
        }

        $this->forgetPublicCache($content);

        $fresh = $content->fresh(['channel.profile', 'dossiers', 'collaborators']);
        $payload = $this->format($fresh);
        $payload['access'] = StudioContentAccess::payload($request->user(), $fresh);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $content = $this->findAccessible($request->user(), $slug);
        abort_unless(StudioContentAccess::isOwner($request->user(), $content), 403);

        $content->clearMedia();
        $content->delete();
        $this->forgetPublicCache($content);

        return response()->json(['success' => true, 'message' => 'Contenu supprimé.']);
    }

    /**
     * Publie le contenu : fige un instantané du brouillon courant dans une nouvelle
     * version et pointe la page publique dessus. `published_as` / `channel_id` ne
     * sont pris en compte qu'à la 1re publication (auteur verrouillé ensuite).
     */
    public function publish(Request $request, PublishStudioContentAction $action, string $slug): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'publication',
            StudioContentAccessLevelEnum::Write,
        );

        $data = $request->validate([
            'published_as' => 'nullable|string|in:user,channel',
            'channel_id' => 'nullable|integer|exists:channels,id',
            'dossier_ids' => 'sometimes|array',
            'dossier_ids.*' => 'integer|exists:dossiers,id',
            /** Force la mise en ligne immédiate même si une date future est enregistrée. */
            'immediate' => 'sometimes|boolean',
        ]);

        $content = $action->execute(
            $content,
            $request->user(),
            $data['published_as'] ?? null,
            $data['channel_id'] ?? null,
            (bool) ($data['immediate'] ?? false),
        );

        // Placement dans les dossiers éditoriaux (facultatif, non versionné).
        if ($request->has('dossier_ids')) {
            $content->dossiers()->sync($data['dossier_ids'] ?? []);
        }

        $this->forgetPublicCache($content);

        return response()->json(['success' => true, 'data' => $this->format($content->fresh())]);
    }

    /**
     * Dépublie : retire la page du public (les versions et le brouillon sont conservés).
     */
    public function unpublish(Request $request, string $slug): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'publication',
            StudioContentAccessLevelEnum::Write,
        );
        $content->update([
            'status' => 'draft',
            'scheduled_publish_at' => null,
        ]);
        $this->forgetPublicCache($content);

        return response()->json(['success' => true, 'data' => $this->format($content->fresh())]);
    }

    /**
     * Journal des versions publiées (métadonnées seulement — pas de payload).
     */
    public function versions(Request $request, string $slug): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'historique',
            StudioContentAccessLevelEnum::Read,
        );

        $rows = $content->versions()
            ->with(['publishedBy.profile', 'channel.profile'])
            ->orderByDesc('version')
            ->get()
            ->map(fn (StudioContentVersion $v) => [
                'version' => $v->version,
                'title' => $v->title,
                'created_at' => $v->created_at?->toIso8601String(),
                'published_as' => $v->published_as,
                'author_name' => $v->published_as === 'channel'
                    ? ($v->channel?->profile?->name ?: 'Chaîne')
                    : (trim(($v->publishedBy?->profile?->first_name ?? '').' '.($v->publishedBy?->profile?->last_name ?? '')) ?: 'Anonyme'),
                'is_current' => $v->version === $content->published_version,
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * Recharge une version antérieure dans le brouillon de travail. La page publique
     * reste sur la version en ligne tant que l'auteur n'a pas re-cliqué « Publier ».
     */
    public function restoreVersion(Request $request, string $slug, int $version): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'historique',
            StudioContentAccessLevelEnum::Write,
        );
        $target = $content->versions()->where('version', $version)->firstOrFail();

        $content->update($target->payload());

        return response()->json(['success' => true, 'data' => $this->format($content->fresh())]);
    }

    /**
     * Dossiers éditoriaux dans lesquels ce contenu est actuellement rangé.
     */
    public function dossiers(Request $request, string $slug): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'publication',
            StudioContentAccessLevelEnum::Read,
        );

        return response()->json(['success' => true, 'data' => $this->formatDossiers($content)]);
    }

    /**
     * Range le contenu dans un ensemble de dossiers (remplace l'ensemble courant).
     * Placement vivant, indépendant du versioning de publication.
     */
    public function syncDossiers(Request $request, string $slug): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'publication',
            StudioContentAccessLevelEnum::Write,
        );

        $data = $request->validate([
            'dossier_ids' => 'present|array',
            'dossier_ids.*' => 'integer|exists:dossiers,id',
        ]);

        $content->dossiers()->sync($data['dossier_ids']);
        $this->forgetPublicCache($content);

        return response()->json(['success' => true, 'data' => $this->formatDossiers($content->fresh())]);
    }

    /**
     * Dossiers suggérés pour ce contenu (correspondance titre + catégories).
     */
    public function dossierSuggestions(Request $request, SuggestDossiersAction $action, string $slug): JsonResponse
    {
        $content = $this->findAccessible(
            $request->user(),
            $slug,
            'publication',
            StudioContentAccessLevelEnum::Read,
        );

        $suggestions = $action->execute($content->title, $content->categories ?? [])
            ->map(fn ($d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'description' => $d->description,
                'image_url' => $d->image_url,
                'category_slugs' => $d->contentCategories->pluck('slug')->values(),
            ]);

        return response()->json(['success' => true, 'data' => $suggestions]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatDossiers(StudioContent $content): array
    {
        return $content->dossiers()
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'image_url' => $d->image_url,
            ])
            ->all();
    }

    private function forgetPublicCache(StudioContent $content): void
    {
        // Le listing public est mis en cache sous des clés combinant type/chaîne/sous-marque/
        // catégories (voir indexPublic()) : impossible d'énumérer toutes les variantes à la
        // sauvegarde, donc on les regroupe sous un tag pour tout invalider en un coup.
        // Fallback sans tags (ex. CACHE_STORE=database en prod mal configuré) : on oublie
        // les clés connues pour éviter une 500, au prix d'une invalidation incomplète.
        if (Cache::supportsTags()) {
            Cache::tags(self::PUBLIC_CACHE_TAG)->flush();

            return;
        }

        Cache::forget('studio.public.index');
        Cache::forget("studio.public.index.{$content->type}");
        Cache::forget("studio.public.show.{$content->slug}");
        Cache::forget("studio.public.show.{$content->id}");
    }

    /**
     * Cache public taggé quand le store le permet (redis) ; sinon remember simple
     * pour ne pas 500 si CACHE_STORE=database/array.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function rememberPublic(string $key, callable $callback): mixed
    {
        if (Cache::supportsTags()) {
            return Cache::tags(self::PUBLIC_CACHE_TAG)->remember($key, self::PUBLIC_CACHE_TTL, $callback);
        }

        return Cache::remember($key, self::PUBLIC_CACHE_TTL, $callback);
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
        $content = StudioContent::with(['user.profile', 'channel.profile', 'publishedVersion'])
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

            return $content;
        }

        // Intégration désactivée : seuls les éditeurs du contenu peuvent encore
        // résoudre un bloc (aperçu Studio / article en brouillon de l'auteur).
        if (! ($content->embed_enabled ?? true)) {
            $viewer = $request->user('sanctum');
            abort_unless($viewer !== null && $viewer->can('update', $content), 404);
        }

        // Contenu publié : le bloc réutilisé provient de la version en ligne.
        return $content->applyPublishedPayload();
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
     * Blocs du contenu dans l'ordre de lecture (cf. StudioContentBlocks::ordered).
     *
     * @return list<array<string,mixed>>
     */
    private function orderedBlocks(StudioContent $content): array
    {
        return StudioContentBlocks::ordered($content);
    }

    private function findAccessible(
        User $user,
        string $slug,
        ?string $resource = null,
        ?StudioContentAccessLevelEnum $level = null,
    ): StudioContent {
        $content = StudioContent::with(['channel.profile', 'dossiers', 'collaborators'])
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();

        if ($resource !== null && $level !== null) {
            abort_unless(
                StudioContentAccess::canAccess($user, $content, $resource, $level),
                403,
            );
        } else {
            abort_unless(StudioContentAccess::canView($user, $content), 403);
        }

        return $content;
    }

    /**
     * Vérifie les permissions write selon les champs présents dans le payload update.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertUpdatePermissions(User $user, StudioContent $content, array $data, Request $request): void
    {
        $studioKeys = ['pages', 'sections', 'blocks', 'card_block_id'];
        $publicationKeys = [
            'published_as',
            'channel_id',
            'scheduled_publish_at',
            'comments_enabled',
            'download_enabled',
            'embed_enabled',
            'response_deadline',
        ];
        $contenuKeys = [
            'title',
            'description',
            'slug',
            'categories',
            'coverage',
            'sub_brand',
            'survey_kind',
            'requires_identity_verification',
            'petition_goal',
            'petition_target',
        ];

        $needsStudio = array_intersect($studioKeys, array_keys($data)) !== [];
        $needsPublication = array_intersect($publicationKeys, array_keys($data)) !== [];
        $needsContenu = array_intersect($contenuKeys, array_keys($data)) !== []
            || $request->hasFile('thumbnail')
            || $request->filled('thumbnail_media_id')
            || $request->boolean('remove_thumbnail');

        if ($needsStudio) {
            abort_unless(
                StudioContentAccess::canAccess($user, $content, 'studio', StudioContentAccessLevelEnum::Write),
                403,
            );
        }
        if ($needsPublication) {
            abort_unless(
                StudioContentAccess::canAccess($user, $content, 'publication', StudioContentAccessLevelEnum::Write),
                403,
            );
        }
        if ($needsContenu) {
            abort_unless(
                StudioContentAccess::canAccess($user, $content, 'contenu', StudioContentAccessLevelEnum::Write),
                403,
            );
        }

        // Payload vide ou non classé : au moins une permission write quelconque.
        if (! $needsStudio && ! $needsPublication && ! $needsContenu) {
            abort_unless(
                StudioContentAccess::canAccess($user, $content, 'contenu', StudioContentAccessLevelEnum::Write)
                || StudioContentAccess::canAccess($user, $content, 'studio', StudioContentAccessLevelEnum::Write)
                || StudioContentAccess::canAccess($user, $content, 'publication', StudioContentAccessLevelEnum::Write),
                403,
            );
        }
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
                ->with(['dataSource.provenance', 'latestVersion'])
                ->where(fn ($q) => $q
                    ->where('user_id', $content->user_id)
                    ->orWhereHas('dataSource.users', fn ($u) => $u->where('user_id', $content->user_id)))
                ->get(['id', 'name', 'row_count', 'data_source_id'])
                ->map(fn ($d) => array_merge([
                    'id' => $d->id,
                    'name' => $d->name,
                    'row_count' => $d->row_count,
                    'provenance' => ContentDatasetSources::provenanceLabel($d),
                    'downloadable' => (bool) ($content->download_enabled ?? true)
                        && ! $d->isLive()
                        && filled($d->latestVersion?->parquet_storage_path),
                ], ContentDatasetSources::freshnessPayload($d)))
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
            'thumbnail_url' => $content->getFirstMediaUrl('thumbnail'),
            'slug' => $content->slug,
            'categories' => $content->categories ?? [],
            'card_block_id' => $content->card_block_id,
            'coverage' => $content->coverage,
            'sub_brand' => $content->sub_brand?->value ?? 'statsio',
            'response_deadline' => $content->response_deadline?->toIso8601String(),
            'scheduled_publish_at' => $content->scheduled_publish_at?->toIso8601String(),
            'comments_enabled' => (bool) ($content->comments_enabled ?? true),
            'download_enabled' => (bool) ($content->download_enabled ?? true),
            'embed_enabled' => (bool) ($content->embed_enabled ?? true),
            'published_as' => $content->published_as,
            'channel_id' => $content->channel_id,
            'published_version' => $content->published_version,
            'first_published_at' => $content->first_published_at?->toIso8601String(),
            'last_published_at' => $content->last_published_at?->toIso8601String(),
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
            // Accès propriété = chargement paresseux si la relation n'est pas déjà eager-loaded.
            'dossiers' => $content->dossiers->map(fn ($d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'image_url' => $d->image_url,
            ])->values(),
            'datasets' => $datasets,
            'pages' => $content->pages ?? [],
            'sections' => $content->sections ?? [],
            'blocks' => $content->blocks ?? [],
            'created_at' => $content->created_at->toIso8601String(),
            'updated_at' => $content->updated_at->toIso8601String(),
        ];
    }
}
