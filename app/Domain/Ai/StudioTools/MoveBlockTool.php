<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;

class MoveBlockTool implements StudioAgentTool
{
    public function name(): string
    {
        return 'move_block';
    }

    public function description(): string
    {
        return 'Déplace un bloc vers une autre section / colonne.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'block_ref' => ['type' => 'string'],
                'to_section_ref' => ['type' => 'string'],
                'col' => ['type' => 'integer'],
            ],
            'required' => ['block_ref', 'to_section_ref', 'col'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $blockRef = trim((string) ($input['block_ref'] ?? ''));
        $toSectionRef = trim((string) ($input['to_section_ref'] ?? ''));
        $col = (int) ($input['col'] ?? 0);

        $block = $context->block($blockRef);
        if ($block === null) {
            return ['error' => "Bloc « {$blockRef} » inconnu."];
        }
        if ($block['locked']) {
            return ['error' => "Le bloc « {$blockRef} » est verrouillé."];
        }

        $section = $context->section($toSectionRef);
        if ($section === null) {
            return ['error' => "Section « {$toSectionRef} » inconnue."];
        }
        if ($col < 0 || $col >= $section['cols']) {
            return ['error' => "col hors limites : la section a {$section['cols']} colonne(s)."];
        }

        $context->registerBlock($blockRef, $block['type'], $toSectionRef, $col, $block['locked']);
        $context->pushOp(['op' => 'moveBlock', 'blockRef' => $blockRef, 'toSectionRef' => $toSectionRef, 'col' => $col]);

        return ['ok' => true];
    }
}
