<?php

namespace App\Domain\DataIngestion\Query;

use App\Domain\DataIngestion\Exceptions\InvalidQueryGraphException;

/**
 * Décrit le graphe multi-sources d'un bloc Studio : N sources (chacune un
 * dataset, avec un id local stable) reliées par des jointures entre ids de
 * sources. Une source est la « primaire » (table du FROM) ; toutes les autres
 * doivent être atteignables via une chaîne de jointures.
 *
 * Sert aussi de shim de compatibilité : construit depuis l'ancien format de
 * params (`joins[i][dataset_id|left_column|right_column|type]` rattachés à la
 * source primaire), il produit le même graphe en étoile qu'avant.
 *
 * Aucune dépendance base de données — la résolution des datasets / du stockage
 * reste au niveau du contrôleur.
 */
final class QueryGraph
{
    /**
     * @param  array<int, array{id: string, dataset_id: int, alias: ?string}>  $sources
     * @param  array<int, array{left_source: string, left_column: string, right_source: string, right_column: string, type: string}>  $joins
     * @param  array<int, array{source_id: string, column: string}>  $legacyProjection  colonnes de jointure à toujours projeter (ancien format)
     */
    private function __construct(
        private readonly array $sources,
        private readonly array $joins,
        private readonly string $primarySourceId,
        private readonly array $legacyProjection = [],
    ) {}

    /**
     * @param  array<int, mixed>  $rawSources  nouveau format : [{id, dataset_id, primary?, alias?}]
     * @param  array<int, mixed>  $rawJoins  nouveau : [{left_source, left_column, right_source, right_column, type}]
     *                                       ancien  : [{dataset_id, left_column, right_column, columns[], type}]
     * @param  int  $primaryDatasetId  dataset de l'URL — sert de source primaire quand $rawSources est vide
     */
    public static function fromRequest(array $rawSources, array $rawJoins, int $primaryDatasetId): self
    {
        $rawSources = array_values(array_filter($rawSources, 'is_array'));
        $rawJoins = array_values(array_filter($rawJoins, 'is_array'));

        // ─── Nouveau format ──────────────────────────────────────────────────
        if ($rawSources !== []) {
            $sources = [];
            $seenIds = [];
            $primaryId = null;
            foreach ($rawSources as $s) {
                $datasetId = (int) ($s['dataset_id'] ?? 0);
                $id = (string) ($s['id'] ?? $datasetId);
                if ($id === '' || $datasetId === 0 || isset($seenIds[$id])) {
                    continue;
                }
                $seenIds[$id] = true;
                $alias = isset($s['alias']) && $s['alias'] !== '' ? (string) $s['alias'] : null;
                $sources[] = ['id' => $id, 'dataset_id' => $datasetId, 'alias' => $alias];
                if (! empty($s['primary'])) {
                    $primaryId = $id;
                }
            }

            if ($sources === []) {
                throw new InvalidQueryGraphException('Aucune source valide.');
            }
            $primaryId ??= $sources[0]['id'];

            $joins = [];
            foreach ($rawJoins as $j) {
                $left = (string) ($j['left_source'] ?? '');
                $right = (string) ($j['right_source'] ?? '');
                if ($left === '' || $right === '' || ! isset($seenIds[$left]) || ! isset($seenIds[$right])) {
                    continue;
                }
                $joins[] = [
                    'left_source' => $left,
                    'left_column' => (string) ($j['left_column'] ?? ''),
                    'right_source' => $right,
                    'right_column' => (string) ($j['right_column'] ?? ''),
                    'type' => in_array($j['type'] ?? '', ['inner', 'left'], true) ? (string) $j['type'] : 'left',
                ];
            }

            return new self($sources, $joins, $primaryId);
        }

        // ─── Ancien format (étoile autour du dataset de l'URL) ───────────────
        $primaryId = (string) $primaryDatasetId;
        $sources = [['id' => $primaryId, 'dataset_id' => $primaryDatasetId, 'alias' => null]];
        $joins = [];
        $legacyProjection = [];
        $seen = [$primaryId => true];

        foreach ($rawJoins as $j) {
            $datasetId = (int) ($j['dataset_id'] ?? 0);
            if ($datasetId === 0) {
                continue;
            }
            $sid = (string) $datasetId;
            if (! isset($seen[$sid])) {
                $seen[$sid] = true;
                $sources[] = ['id' => $sid, 'dataset_id' => $datasetId, 'alias' => null];
            }
            $joins[] = [
                'left_source' => $primaryId,
                'left_column' => (string) ($j['left_column'] ?? ''),
                'right_source' => $sid,
                'right_column' => (string) ($j['right_column'] ?? ''),
                'type' => in_array($j['type'] ?? '', ['inner', 'left'], true) ? (string) $j['type'] : 'left',
            ];
            foreach ((array) ($j['columns'] ?? []) as $col) {
                $legacyProjection[] = ['source_id' => $sid, 'column' => (string) $col];
            }
        }

        return new self($sources, $joins, $primaryId, $legacyProjection);
    }

    public function isMultiSource(): bool
    {
        return count($this->sources) > 1;
    }

    public function primarySourceId(): string
    {
        return $this->primarySourceId;
    }

    public function primaryDatasetId(): int
    {
        return $this->datasetIdFor($this->primarySourceId);
    }

    /** @return array<int, array{id: string, dataset_id: int, alias: ?string}> */
    public function sources(): array
    {
        return $this->sources;
    }

    /** @return array<int, int> */
    public function datasetIds(): array
    {
        return array_values(array_unique(array_map(fn ($s) => $s['dataset_id'], $this->sources)));
    }

    public function datasetIdFor(string $sourceId): int
    {
        foreach ($this->sources as $s) {
            if ($s['id'] === $sourceId) {
                return $s['dataset_id'];
            }
        }
        throw new InvalidQueryGraphException("Source inconnue : {$sourceId}");
    }

    public function aliasFor(string $sourceId): string
    {
        $i = 0;
        foreach ($this->sources as $s) {
            if ($s['id'] === $sourceId) {
                return 't'.$i;
            }
            $i++;
        }
        throw new InvalidQueryGraphException("Source inconnue : {$sourceId}");
    }

    public function labelFor(string $sourceId): string
    {
        foreach ($this->sources as $s) {
            if ($s['id'] === $sourceId) {
                return $s['alias'] ?? $s['id'];
            }
        }

        return $sourceId;
    }

    /**
     * Résout une référence de colonne `name` (= primaire) ou `name@<sourceId>`.
     *
     * @return array{name: string, source_id: string, alias: string, is_primary: bool}
     */
    public function resolveRef(string $ref): array
    {
        $at = strrpos($ref, '@');
        if ($at !== false) {
            $suffix = substr($ref, $at + 1);
            foreach ($this->sources as $s) {
                if ($s['id'] === $suffix) {
                    return [
                        'name' => substr($ref, 0, $at),
                        'source_id' => $suffix,
                        'alias' => $this->aliasFor($suffix),
                        'is_primary' => $suffix === $this->primarySourceId,
                    ];
                }
            }
        }

        // Nom nu (ou suffixe inconnu) ⇒ colonne de la source primaire.
        return [
            'name' => $ref,
            'source_id' => $this->primarySourceId,
            'alias' => $this->aliasFor($this->primarySourceId),
            'is_primary' => true,
        ];
    }

    /**
     * Jointures triées : chaque entrée relie une source déjà présente dans le
     * FROM (`from_*`) à une nouvelle source (`to_*`). Arbre couvrant BFS depuis
     * la primaire. Lève si une source reste injoignable (graphe disjoint).
     *
     * @return array<int, array{
     *   to_source: string, to_alias: string, to_dataset_id: int,
     *   from_alias: string, from_column: string, to_column: string, type: string
     * }>
     */
    public function orderedJoins(): array
    {
        if (! $this->isMultiSource()) {
            return [];
        }

        $visited = [$this->primarySourceId => true];
        $ordered = [];
        $remaining = $this->joins;

        $progress = true;
        while ($progress && count($visited) < count($this->sources)) {
            $progress = false;
            foreach ($remaining as $k => $join) {
                $left = $join['left_source'];
                $right = $join['right_source'];
                $leftIn = isset($visited[$left]);
                $rightIn = isset($visited[$right]);

                if ($leftIn === $rightIn) {
                    // les deux déjà vus (jointure redondante) ou aucun des deux (pas encore atteignable)
                    if ($leftIn && $rightIn) {
                        unset($remaining[$k]);
                    }

                    continue;
                }

                $fromSource = $leftIn ? $left : $right;
                $toSource = $leftIn ? $right : $left;
                $fromColumn = $leftIn ? $join['left_column'] : $join['right_column'];
                $toColumn = $leftIn ? $join['right_column'] : $join['left_column'];

                $visited[$toSource] = true;
                $ordered[] = [
                    'to_source' => $toSource,
                    'to_alias' => $this->aliasFor($toSource),
                    'to_dataset_id' => $this->datasetIdFor($toSource),
                    'from_alias' => $this->aliasFor($fromSource),
                    'from_column' => $fromColumn,
                    'to_column' => $toColumn,
                    'type' => $join['type'],
                ];
                unset($remaining[$k]);
                $progress = true;
            }
        }

        if (count($visited) < count($this->sources)) {
            throw new InvalidQueryGraphException(
                'Certaines sources ne sont reliées à la source principale par aucune jointure.'
            );
        }

        return $ordered;
    }

    /** @return array<int, array{source_id: string, column: string}> */
    public function legacyProjection(): array
    {
        return $this->legacyProjection;
    }

    /**
     * Sérialisable pour la clé de cache (ordre stable).
     *
     * @return array<string, mixed>
     */
    public function cacheSignature(): array
    {
        return [
            'sources' => $this->sources,
            'joins' => $this->joins,
            'primary' => $this->primarySourceId,
        ];
    }
}
