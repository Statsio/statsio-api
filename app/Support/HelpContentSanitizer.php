<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Nettoie le HTML produit par le RichEditor Filament (articles du centre
 * d'aide) avant enregistrement : retire tout script/comportement dangereux
 * tout en gardant la mise en forme, les liens et les images uploadées.
 */
final class HelpContentSanitizer
{
    public static function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->withMaxInputLength(200_000);

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
