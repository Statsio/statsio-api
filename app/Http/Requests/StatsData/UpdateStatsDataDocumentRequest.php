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
            'visibility' => ['sometimes', Rule::in(StatsDataVisibility::values())],
            'blocks' => 'sometimes|array',
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
        if (array_key_exists('blocks', $v)) {
            $out['blocks'] = $v['blocks'];
        }

        return $out;
    }
}
