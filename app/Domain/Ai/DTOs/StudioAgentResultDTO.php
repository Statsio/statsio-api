<?php

namespace App\Domain\Ai\DTOs;

use App\Services\Ai\LlmMessage;

/**
 * Résultat de la boucle d'agent pour un message utilisateur.
 */
readonly class StudioAgentResultDTO
{
    /**
     * @param  array<int,array<string,mixed>>  $patchOps  Ops à appliquer sur le store front.
     * @param  int[]  $attachedDatasetIds  Datasets rendus accessibles pendant le run.
     * @param  LlmMessage[]  $transcript  Tours model/tool à persister pour rejouer l'historique.
     * @param  array<string,int>  $usage
     */
    public function __construct(
        public string $assistantMessage,
        public array $patchOps,
        public array $attachedDatasetIds,
        public array $transcript,
        public array $usage,
    ) {}
}
