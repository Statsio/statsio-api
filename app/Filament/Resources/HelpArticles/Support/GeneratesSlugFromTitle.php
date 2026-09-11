<?php

namespace App\Filament\Resources\HelpArticles\Support;

use App\Models\Help\HelpArticle;
use App\Support\HelpContentSanitizer;
use Illuminate\Support\Str;

trait GeneratesSlugFromTitle
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = $this->uniqueSlug($data['title'] ?? '', $data['help_category_id'] ?? null);
        $data['content_html'] = HelpContentSanitizer::sanitize($data['content_html'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['title'])) {
            $data['slug'] = $this->uniqueSlug(
                $data['title'],
                $data['help_category_id'] ?? $this->record?->help_category_id,
                $this->record?->getKey(),
            );
        }

        $data['content_html'] = HelpContentSanitizer::sanitize($data['content_html'] ?? null);

        return $data;
    }

    private function uniqueSlug(string $title, ?int $categoryId, ?int $ignoreId = null): string
    {
        $base = mb_substr(Str::slug($title) ?: 'article', 0, 140);
        $slug = $base;
        $i = 2;

        while (
            HelpArticle::where('slug', $slug)
                ->where('help_category_id', $categoryId)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = mb_substr($base, 0, 137)."-{$i}";
            $i++;
        }

        return $slug;
    }
}
