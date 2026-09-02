<?php

namespace App\Domain\Ai\StudioTools;

use App\Domain\Ai\StudioAgentContext;

/**
 * Ajoute une section (rangée) à une page.
 */
class AddSectionTool implements StudioAgentTool
{
    private const LAYOUTS = ['1-col', '2-cols', '3-cols', '2-1-cols', '1-2-cols'];

    public function name(): string
    {
        return 'add_section';
    }

    private const THEMES = ['default', 'dark', 'accent'];

    public function description(): string
    {
        return 'Ajoute une section à une page. layout ∈ '.implode(' | ', self::LAYOUTS).'. '
            .'En-tête optionnel : kicker (sur-titre), title, description ; theme (fond) ∈ '
            .implode(' | ', self::THEMES).'. Un title génère automatiquement l\'ancre + l\'entrée du sommaire.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ref' => ['type' => 'string', 'description' => 'Référence locale unique (ex. "s1").'],
                'page_ref' => ['type' => 'string', 'description' => 'Ref ou id de la page cible.'],
                'layout' => ['type' => 'string', 'enum' => self::LAYOUTS],
                'index' => ['type' => 'integer', 'description' => 'Position d\'insertion (optionnel).'],
                'kicker' => ['type' => 'string', 'description' => 'Sur-titre court (ex. "Graphique · Barres").'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'theme' => ['type' => 'string', 'enum' => self::THEMES],
            ],
            'required' => ['ref', 'page_ref', 'layout'],
        ];
    }

    public function execute(array $input, StudioAgentContext $context): array
    {
        $ref = trim((string) ($input['ref'] ?? ''));
        $pageRef = trim((string) ($input['page_ref'] ?? ''));
        $layout = (string) ($input['layout'] ?? '');

        if ($ref === '' || $pageRef === '') {
            return ['error' => 'ref et page_ref sont obligatoires.'];
        }
        if ($context->hasSection($ref)) {
            return ['error' => "La ref de section « {$ref} » est déjà utilisée."];
        }
        if (! $context->hasPage($pageRef)) {
            return ['error' => "Page « {$pageRef} » inconnue. Crée-la d'abord avec add_page."];
        }
        if (! in_array($layout, self::LAYOUTS, true)) {
            return ['error' => 'layout invalide. Attendu : '.implode(', ', self::LAYOUTS)];
        }

        $context->registerSection($ref, $pageRef, $layout);

        $op = ['op' => 'addSection', 'ref' => $ref, 'pageRef' => $pageRef, 'layout' => $layout];
        if (isset($input['index']) && is_numeric($input['index'])) {
            $op['index'] = (int) $input['index'];
        }
        foreach (['kicker', 'title', 'description'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') {
                $op[$key] = $input[$key];
            }
        }
        if (isset($input['theme']) && in_array($input['theme'], self::THEMES, true) && $input['theme'] !== 'default') {
            $op['theme'] = $input['theme'];
        }
        $context->pushOp($op);

        return ['ok' => true, 'ref' => $ref];
    }
}
