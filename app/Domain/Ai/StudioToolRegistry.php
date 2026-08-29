<?php

namespace App\Domain\Ai;

use App\Domain\Ai\StudioTools\AddBlockTool;
use App\Domain\Ai\StudioTools\AddPageTool;
use App\Domain\Ai\StudioTools\AddSectionTool;
use App\Domain\Ai\StudioTools\AttachPublicSourceTool;
use App\Domain\Ai\StudioTools\GetDatasetSchemaTool;
use App\Domain\Ai\StudioTools\ListSourcesTool;
use App\Domain\Ai\StudioTools\MoveBlockTool;
use App\Domain\Ai\StudioTools\RemoveBlockTool;
use App\Domain\Ai\StudioTools\SearchPublicCatalogTool;
use App\Domain\Ai\StudioTools\StudioAgentTool;
use App\Domain\Ai\StudioTools\UpdateBlockTool;

/**
 * Registre des outils exposés au modèle dans la boucle d'agent :
 * lecture (sources / schéma / catalogue) + écriture (accumulent des ops de patch).
 */
class StudioToolRegistry
{
    /** @var array<string,StudioAgentTool> */
    private array $tools = [];

    public function __construct(
        ListSourcesTool $listSources,
        SearchPublicCatalogTool $searchPublicCatalog,
        GetDatasetSchemaTool $getDatasetSchema,
        AttachPublicSourceTool $attachPublicSource,
        AddPageTool $addPage,
        AddSectionTool $addSection,
        AddBlockTool $addBlock,
        UpdateBlockTool $updateBlock,
        RemoveBlockTool $removeBlock,
        MoveBlockTool $moveBlock,
    ) {
        foreach ([
            $listSources, $searchPublicCatalog, $getDatasetSchema, $attachPublicSource,
            $addPage, $addSection, $addBlock, $updateBlock, $removeBlock, $moveBlock,
        ] as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * @return array<int,array{name:string,description:string,parameters:array<string,mixed>}>
     */
    public function definitions(): array
    {
        return array_values(array_map(
            fn (StudioAgentTool $tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ],
            $this->tools,
        ));
    }

    public function get(string $name): ?StudioAgentTool
    {
        return $this->tools[$name] ?? null;
    }
}
