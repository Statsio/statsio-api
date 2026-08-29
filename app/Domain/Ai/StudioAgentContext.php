<?php

namespace App\Domain\Ai;

use App\Models\StudioContent;
use App\Models\User\User;

/**
 * État partagé d'un run de l'assistant Studio, passé à chaque outil.
 *
 * Porte :
 *  - l'utilisateur et le contenu (autorisation, contexte) ;
 *  - un modèle en mémoire de l'arbre de travail (pages / sections / blocs), seedé
 *    depuis le contenu et enrichi au fil des outils d'écriture. Les éléments
 *    existants gardent leur id réel ; les nouveaux reçoivent une *ref* inventée
 *    par le modèle. Sert à valider les ops (ref connue ? bloc verrouillé ? …) ;
 *  - la liste ordonnée des *ops de patch* que le front appliquera sur useStudioStore ;
 *  - les ids de datasets rattachés pendant le run.
 */
class StudioAgentContext
{
    /** @var array<string,array{ref:string}> */
    private array $pages = [];

    /** @var array<string,array{ref:string,pageRef:string,layout:string,cols:int}> */
    private array $sections = [];

    /** @var array<string,array{ref:string,type:string,sectionRef:string,col:int,locked:bool,loopRef:?string}> */
    private array $blocks = [];

    /** @var array<int,array<string,mixed>> */
    private array $patchOps = [];

    /** @var int[] */
    private array $attachedDatasetIds = [];

    private const SECTION_COLS = [
        '1-col' => 1, '2-cols' => 2, '3-cols' => 3, '2-1-cols' => 2, '1-2-cols' => 2,
    ];

    public function __construct(
        public readonly User $user,
        public readonly StudioContent $content,
    ) {
        $this->seedFromContent();
    }

    public function contentType(): string
    {
        return $this->content->type ?? 'statsdata';
    }

    // ─── Arbre de travail ────────────────────────────────────────────────────

    public function hasPage(string $ref): bool
    {
        return isset($this->pages[$ref]);
    }

    public function hasSection(string $ref): bool
    {
        return isset($this->sections[$ref]);
    }

    /** @return array{ref:string,type:string,sectionRef:string,col:int,locked:bool,loopRef:?string}|null */
    public function block(string $ref): ?array
    {
        return $this->blocks[$ref] ?? null;
    }

    /** Un bloc conteneur (`loop` ou `if`) accueille des enfants via `loop_ref`. */
    public function blockIsContainer(string $ref): bool
    {
        return in_array($this->blocks[$ref]['type'] ?? null, ['loop', 'if'], true);
    }

    /** @return array{ref:string,pageRef:string,layout:string,cols:int}|null */
    public function section(string $ref): ?array
    {
        return $this->sections[$ref] ?? null;
    }

    public function registerPage(string $ref): void
    {
        $this->pages[$ref] = ['ref' => $ref];
    }

    public function registerSection(string $ref, string $pageRef, string $layout): void
    {
        $this->sections[$ref] = [
            'ref' => $ref,
            'pageRef' => $pageRef,
            'layout' => $layout,
            'cols' => self::SECTION_COLS[$layout] ?? 1,
        ];
    }

    public function registerBlock(string $ref, string $type, string $sectionRef, int $col, bool $locked = false, ?string $loopRef = null): void
    {
        $this->blocks[$ref] = compact('ref', 'type', 'sectionRef', 'col', 'locked', 'loopRef');
    }

    public function forgetBlock(string $ref): void
    {
        unset($this->blocks[$ref]);
    }

    // ─── Patch & rattachements ───────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $op
     */
    public function pushOp(array $op): void
    {
        $this->patchOps[] = $op;
    }

    /** @return array<int,array<string,mixed>> */
    public function patchOps(): array
    {
        return $this->patchOps;
    }

    public function markDatasetAttached(int $datasetId): void
    {
        if (! in_array($datasetId, $this->attachedDatasetIds, true)) {
            $this->attachedDatasetIds[] = $datasetId;
        }
    }

    /** @return int[] */
    public function attachedDatasetIds(): array
    {
        return $this->attachedDatasetIds;
    }

    // ─── Seed ────────────────────────────────────────────────────────────────

    private function seedFromContent(): void
    {
        $pages = $this->content->pages ?: [['id' => 'default', 'title' => 'Page 1']];
        foreach ($pages as $page) {
            $id = (string) ($page['id'] ?? 'default');
            $this->pages[$id] = ['ref' => $id];
        }

        foreach ($this->content->sections ?? [] as $section) {
            $id = (string) ($section['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $layout = (string) ($section['layout'] ?? '1-col');
            $this->sections[$id] = [
                'ref' => $id,
                'pageRef' => (string) ($section['pageId'] ?? 'default'),
                'layout' => $layout,
                'cols' => self::SECTION_COLS[$layout] ?? 1,
            ];
        }

        foreach ($this->content->blocks ?? [] as $block) {
            $id = (string) ($block['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $zoneId = (string) ($block['zoneId'] ?? '');
            $loopRef = null;
            if (preg_match('/^loop:(.+):0$/', $zoneId, $m) === 1) {
                $loopRef = $m[1];
                [$sectionRef, $col] = ['', 0];
            } else {
                [$sectionRef, $col] = $this->splitZone($zoneId);
            }
            $this->blocks[$id] = [
                'ref' => $id,
                'type' => (string) ($block['type'] ?? ''),
                'sectionRef' => $sectionRef,
                'col' => $col,
                'locked' => (bool) ($block['locked'] ?? false),
                'loopRef' => $loopRef,
            ];
        }
    }

    /**
     * @return array{0:string,1:int}
     */
    private function splitZone(string $zoneId): array
    {
        $pos = strrpos($zoneId, '-');
        if ($pos === false) {
            return [$zoneId, 0];
        }

        return [substr($zoneId, 0, $pos), (int) substr($zoneId, $pos + 1)];
    }
}
