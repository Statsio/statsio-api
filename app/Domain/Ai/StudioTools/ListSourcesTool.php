<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\Ai\Support\StudioSourceReader;

/**
 * Liste les datasets que l'utilisateur peut déjà lier à un bloc (les siens +
 * sources publiques rattachées), avec schéma résumé et rôles sémantiques.
 */
class ListSourcesTool implements StudioAgentTool
{
    public function __construct(private readonly StudioSourceReader $reader) {}

    public function name(): string
    {
        return 'list_sources';
    }

    public function description(): string
    {
        return 'Liste les sources de données déjà accessibles à l\'utilisateur (colonnes, types, '
            .'rôle sémantique, nombre de lignes). À utiliser avant de proposer un bloc de données.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        return ['datasets' => $this->reader->accessibleDatasets($context->user)];
    }
}
