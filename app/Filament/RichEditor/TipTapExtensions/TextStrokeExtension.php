<?php

namespace App\Filament\RichEditor\TipTapExtensions;

use Illuminate\Support\Str;
use Tiptap\Core\Mark;

/**
 * Mark TipTap PHP miroir de resources/js/dist/filament/rich-content-plugins/text-stroke.js
 * — contour (`-webkit-text-stroke`) appliqué à la sélection, pas à toute la ligne.
 */
class TextStrokeExtension extends Mark
{
    /**
     * @var string
     */
    public static $name = 'textStroke';

    private const STROKE_WIDTH = '1px';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseHTML(): array
    {
        return [
            [
                'tag' => 'span',
                'getAttrs' => fn ($DOMNode): bool => in_array('text-stroke', explode(' ', (string) $DOMNode->getAttribute('class')), true),
            ],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function addAttributes(): array
    {
        return [
            'data-stroke-color' => [
                'parseHTML' => fn ($DOMNode) => $DOMNode->getAttribute('data-stroke-color') ?: null,
                'renderHTML' => function ($attributes) {
                    $value = null;

                    if (is_array($attributes)) {
                        $value = $attributes['data-stroke-color'] ?? null;
                    } elseif (is_object($attributes)) {
                        $value = $attributes->{'data-stroke-color'} ?? ($attributes->dataStrokeColor ?? null);
                    }

                    return [
                        'data-stroke-color' => $value,
                    ];
                },
            ],
        ];
    }

    /**
     * @param  object  $mark
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    public function renderHTML($mark, $HTMLAttributes = []): array
    {
        $existingClass = isset($HTMLAttributes['class']) ? (string) $HTMLAttributes['class'] : '';
        $HTMLAttributes['class'] = trim(implode(' ', array_filter(['text-stroke', $existingClass])));

        $colorName = $HTMLAttributes['data-stroke-color'] ?? null;
        $sanitizedColor = Str::sanitizeCssColor(is_string($colorName) ? $colorName : null);

        if (filled($sanitizedColor)) {
            $css = "-webkit-text-stroke-color: {$sanitizedColor}; -webkit-text-stroke-width: ".self::STROKE_WIDTH.'; paint-order: stroke fill';
            $existingStyle = isset($HTMLAttributes['style']) ? (string) $HTMLAttributes['style'] : '';
            $HTMLAttributes['style'] = $existingStyle !== '' ? ($css.'; '.$existingStyle) : $css;
        }

        return [
            'span',
            $HTMLAttributes,
            0,
        ];
    }
}
