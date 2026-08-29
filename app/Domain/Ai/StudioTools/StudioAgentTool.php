<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;

/**
 * Un outil exposé au modèle dans la boucle d'agent du Studio.
 *
 * Les outils de lecture renvoient des données ; les outils d'écriture (phase 3)
 * valident puis empilent une op de patch dans le contexte et renvoient un accusé.
 */
interface StudioAgentTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema (objet) des arguments — sous-ensemble compatible Gemini.
     *
     * @return array<string,mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed> Résultat renvoyé au modèle (sérialisé en functionResponse).
     */
    public function execute(array $input, StudioAgentContext $context): array;
}
