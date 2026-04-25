<?php

namespace App\Http\Requests\StatsData;

use App\Domain\StatsData\Enums\StatsDataVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatsDataDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:500',
            'subtitle' => 'nullable|string|max:5000',
            'description' => 'sometimes|nullable|string|max:20000',
            'categories' => 'sometimes|array|max:50',
            'categories.*' => 'string|max:80',
            'tags' => 'sometimes|array|max:50',
            'tags.*' => 'string|max:80',
            'cover_media_id' => 'sometimes|nullable|integer|exists:media,id',
            'visibility' => ['required', Rule::in(StatsDataVisibility::values())],
            'pages' => 'sometimes|array',
            'pages.*.id' => 'required|string|max:100',
            'pages.*.name' => 'required|string|max:200',
            'pages.*.blocks' => 'present|array',
            'pages.*.visible_in_tabs' => 'sometimes|boolean',
            'pages.*.visibility' => ['sometimes', 'string', Rule::in(['inherit', 'public', 'password', 'private'])],
            'pages.*.password' => 'sometimes|nullable|string|max:255',
            'pages.*.order' => 'sometimes|integer|min:0',
        ];
    }

    /**
     * @return array{title: string, subtitle: string, visibility: string, pages: array}
     */
    public function normalizedPayload(): array
    {
        $v = $this->validated();

        return [
            'title' => $v['title'],
            'subtitle' => $v['subtitle'] ?? '',
            'description' => $v['description'] ?? '',
            'categories' => $v['categories'] ?? [],
            'tags' => $v['tags'] ?? [],
            'cover_media_id' => $v['cover_media_id'] ?? null,
            'visibility' => $v['visibility'],
            'pages' => $v['pages'] ?? [['id' => 'page_' . uniqid(), 'name' => 'Page 1', 'blocks' => []]],
        ];
    }
}
