<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\Support\StudioSourceReader;

/**
 * Cherche dans le catalogue public Statsio (sources partagées non encore
 * rattachées au compte). Le rattachement effectif se fait via attach_public_source
 * (phase 4).
 */
class SearchPublicCatalogTool implements StudioAgentTool
{
    public function __construct(private readonly StudioSourceReader $reader) {}

    public function name(): string
    {
        return 'search_public_catalog';
    }

    public function description(): string
    {
        return 'Recherche des sources de données publiques partagées par Statsio (par mot-clé et/ou '
            .'catégorie). Renvoie leur schéma résumé et si elles sont déjà rattachées.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Mot-clé recherché dans le nom de la source.'],
                'category' => ['type' => 'string', 'description' => 'Slug de catégorie de contenu.'],
            ],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        return [
            'sources' => $this->reader->publicCatalog(
                $context->user,
                isset($input['query']) ? (string) $input['query'] : null,
                isset($input['category']) ? (string) $input['category'] : null,
            ),
        ];
    }
}
