<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;

class RemoveBlockTool implements StudioAgentTool
{
    public function name(): string
    {
        return 'remove_block';
    }

    public function description(): string
    {
        return 'Supprime un bloc existant (impossible sur un bloc verrouillé).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'block_ref' => ['type' => 'string', 'description' => 'Ref ou id du bloc.'],
            ],
            'required' => ['block_ref'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $blockRef = trim((string) ($input['block_ref'] ?? ''));
        $block = $context->block($blockRef);

        if ($block === null) {
            return ['error' => "Bloc « {$blockRef} » inconnu."];
        }
        if ($block['locked']) {
            return ['error' => "Le bloc « {$blockRef} » est verrouillé."];
        }

        $context->forgetBlock($blockRef);
        $context->pushOp(['op' => 'removeBlock', 'blockRef' => $blockRef]);

        return ['ok' => true];
    }
}
