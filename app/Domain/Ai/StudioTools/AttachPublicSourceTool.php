<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;
use App\Domain\DataIngestion\Actions\AttachPublicDataSourceAction;
use App\Models\DataIngestion\DataSource;

/**
 * Rattache une source publique du catalogue Statsio au compte de l'utilisateur
 * (sans duplication) et renvoie le dataset_id devenu accessible, à passer ensuite
 * à add_block.
 */
class AttachPublicSourceTool implements StudioAgentTool
{
    public function __construct(private readonly AttachPublicDataSourceAction $attach) {}

    public function name(): string
    {
        return 'attach_public_source';
    }

    public function description(): string
    {
        return 'Rattache une source publique (data_source_id du catalogue) au compte de l\'utilisateur '
            .'et renvoie son dataset_id, utilisable dans add_block.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data_source_id' => ['type' => 'integer'],
            ],
            'required' => ['data_source_id'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $id = (int) ($input['data_source_id'] ?? 0);
        $source = DataSource::with('dataset')->find($id);

        if ($source === null || $source->visibility !== 'public') {
            return ['error' => "Source publique {$id} introuvable."];
        }

        $this->attach->execute($source, $context->user);

        $datasetId = $source->dataset?->id;
        if ($datasetId === null) {
            return ['error' => 'Cette source n\'a pas encore de dataset exploitable.'];
        }

        $context->markDatasetAttached($datasetId);

        return ['ok' => true, 'dataset_id' => $datasetId, 'name' => $source->name];
    }
}
