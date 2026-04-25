<?php

namespace App\Http\Requests\StatsData;

use App\Domain\StatsData\Enums\StatsDataVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatsDataDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:500',
            'subtitle' => 'sometimes|nullable|string|max:5000',
            'description' => 'sometimes|nullable|string|max:20000',
            'categories' => 'sometimes|array|max:50',
            'categories.*' => 'string|max:80',
            'tags' => 'sometimes|array|max:50',
            'tags.*' => 'string|max:80',
            'cover_media_id' => 'sometimes|nullable|integer|exists:media,id',
            'visibility' => ['sometimes', Rule::in(StatsDataVisibility::values())],
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
     * @return array<string, mixed>
     */
    public function normalizedPayload(): array
    {
        $v = $this->validated();
        $out = [];
        if (array_key_exists('title', $v)) {
            $out['title'] = $v['title'];
        }
        if (array_key_exists('subtitle', $v)) {
            $out['subtitle'] = $v['subtitle'] ?? '';
        }
        if (array_key_exists('visibility', $v)) {
            $out['visibility'] = $v['visibility'];
        }
        if (array_key_exists('pages', $v)) {
            $out['pages'] = $v['pages'];
        }
        if (array_key_exists('description', $v)) {
            $out['description'] = $v['description'] ?? '';
        }
        if (array_key_exists('categories', $v)) {
            $out['categories'] = $v['categories'] ?? [];
        }
        if (array_key_exists('tags', $v)) {
            $out['tags'] = $v['tags'] ?? [];
        }
        if (array_key_exists('cover_media_id', $v)) {
            $out['cover_media_id'] = $v['cover_media_id'];
        }

        return $out;
    }
}
