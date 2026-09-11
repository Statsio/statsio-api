<?php

namespace App\Filament\RichEditor\Plugins;

use App\Filament\RichEditor\TipTapExtensions\TextStrokeExtension;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Tiptap\Core\Extension;

/**
 * Contour de lettres (`-webkit-text-stroke`) appliqué à la sélection TipTap,
 * sur le même modèle que le bouton natif « couleur de texte ».
 */
class TextStrokeRichContentPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        return [
            app(TextStrokeExtension::class),
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        return [
            FilamentAsset::getScriptSrc('rich-content-plugins/text-stroke'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('textStroke')
                ->label('Contour')
                ->hiddenLabel(false)
                ->action(arguments: '{ color: $getEditor().getAttributes(\'textStroke\')[\'data-stroke-color\'] ?? null }')
                ->toggle()
                ->icon(Heroicon::OutlinedPaintBrush),
        ];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [
            Action::make('textStroke')
                ->label('Couleur de contour')
                ->modalHeading('Couleur de contour des lettres')
                ->modalWidth(Width::Large)
                ->fillForm(fn (array $arguments): array => [
                    'color' => $arguments['color'] ?? null,
                ])
                ->schema([
                    ColorPicker::make('color')
                        ->label('Couleur de contour')
                        ->helperText('Appliquée uniquement à la sélection. Laissez vide pour retirer le contour.'),
                ])
                ->action(function (array $arguments, array $data, RichEditor $component): void {
                    $isSingleCharacterSelection = ($arguments['editorSelection']['head'] ?? null) === ($arguments['editorSelection']['anchor'] ?? null);
                    $color = $data['color'] ?? null;

                    if (blank($color)) {
                        $component->runCommands(
                            [
                                ...($isSingleCharacterSelection ? [EditorCommand::make(
                                    'extendMarkRange',
                                    arguments: ['textStroke'],
                                )] : []),
                                EditorCommand::make('unsetTextStroke'),
                            ],
                            editorSelection: $arguments['editorSelection'],
                        );

                        return;
                    }

                    $component->runCommands(
                        [
                            ...($isSingleCharacterSelection ? [EditorCommand::make(
                                'extendMarkRange',
                                arguments: ['textStroke'],
                            )] : []),
                            EditorCommand::make(
                                'setTextStroke',
                                arguments: [[
                                    'color' => $color,
                                ]],
                            ),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );
                }),
        ];
    }
}
