<?php

namespace App\Support;

/**
 * Recherche plein-texte multi-mots partagée par le chemin Parquet/DuckDB
 * (DatasetController) et le chemin live (LiveDatasetQueryService).
 *
 * La requête est découpée sur les espaces : chaque mot doit apparaître dans
 * au moins une des colonnes recherchées (OR entre colonnes), et tous les mots
 * sont requis (AND entre mots). L'ordre des mots n'importe pas — « jean dupond »
 * et « dupond jean » matchent la même ligne (prénom=Jean, nom=Dupond).
 * Un seul mot ⇒ comportement identique à l'ancienne recherche « contient ».
 */
trait TokenizedSearch
{
    /**
     * Découpe une requête de recherche en mots (minuscules, sans doublon de séparateurs).
     *
     * @return array<int, string>
     */
    protected function searchTokens(string $q): array
    {
        $parts = preg_split('/\s+/u', mb_strtolower(trim($q))) ?: [];

        return array_values(array_filter($parts, static fn ($t) => $t !== ''));
    }

    /**
     * Une ligne (tableau associatif) matche-t-elle tous les mots de la requête,
     * chaque mot apparaissant dans au moins une des `$columns` ?
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $columns
     */
    protected function rowMatchesAllTokens(array $row, string $searchQ, array $columns): bool
    {
        $tokens = $this->searchTokens($searchQ);
        if ($tokens === [] || $columns === []) {
            return true;
        }

        foreach ($tokens as $token) {
            $found = false;
            foreach ($columns as $col) {
                if (mb_stripos((string) ($row[$col] ?? ''), $token) !== false) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deux groupes de colonnes (« ET » identité + « OU » complémentaires) :
     * un résultat matche si TOUS les mots sont retrouvés dans le groupe identité,
     * OU si tous les mots sont retrouvés dans le groupe complémentaire. Les deux
     * groupes ne se mélangent pas.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $identityCols
     * @param  array<int, string>  $altCols
     */
    protected function rowMatchesSearch(array $row, string $searchQ, array $identityCols, array $altCols): bool
    {
        if ($this->searchTokens($searchQ) === []) {
            return true;
        }
        if ($identityCols === [] && $altCols === []) {
            return true;
        }
        if ($identityCols !== [] && $this->rowMatchesAllTokens($row, $searchQ, $identityCols)) {
            return true;
        }

        return $altCols !== [] && $this->rowMatchesAllTokens($row, $searchQ, $altCols);
    }

    /**
     * Clause SQL combinée pour deux groupes : `(<identité>) OR (<complémentaires>)`
     * (ou l'un des deux seul, ou `null` si les deux sont vides).
     *
     * @param  array<int, string>  $identityCols
     * @param  array<int, string>  $altCols
     * @param  callable(string): ?string  $sqlRef
     */
    protected function buildSearchSql(array $identityCols, array $altCols, string $searchQ, callable $sqlRef): ?string
    {
        $a = $this->buildTokenSearchSql($identityCols, $searchQ, $sqlRef);
        $b = $this->buildTokenSearchSql($altCols, $searchQ, $sqlRef);

        return match (true) {
            $a !== null && $b !== null => "({$a}) OR ({$b})",
            $a !== null => $a,
            $b !== null => $b,
            default => null,
        };
    }

    /**
     * Clause SQL de recherche tokenisée : `(c1 LIKE %mot1% OR c2 LIKE %mot1%) AND (…mot2…)`.
     * `$sqlRef` transforme un nom de colonne en expression SQL (qualifiée / échappée).
     * Renvoie `null` si rien à filtrer.
     *
     * @param  array<int, string>  $columns
     * @param  callable(string): ?string  $sqlRef
     */
    protected function buildTokenSearchSql(array $columns, string $searchQ, callable $sqlRef): ?string
    {
        $tokens = $this->searchTokens($searchQ);
        if ($tokens === [] || $columns === []) {
            return null;
        }

        $refs = array_values(array_filter(array_map($sqlRef, $columns)));
        if ($refs === []) {
            return null;
        }

        $andGroups = [];
        foreach ($tokens as $token) {
            $lit = "'".str_replace("'", "''", $token)."'";
            $ors = array_map(
                static fn ($ref) => "LOWER({$ref}::VARCHAR) LIKE LOWER(CONCAT('%', {$lit}, '%'))",
                $refs,
            );
            $andGroups[] = '('.implode(' OR ', $ors).')';
        }

        return implode(' AND ', $andGroups);
    }
}
