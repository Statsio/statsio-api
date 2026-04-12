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
            'visibility' => ['required', Rule::in(StatsDataVisibility::values())],
            'blocks' => 'sometimes|array',
        ];
    }

    /**
     * @return array{title: string, subtitle: string, visibility: string, blocks: array}
     */
    public function normalizedPayload(): array
    {
        $v = $this->validated();

        return [
            'title' => $v['title'],
            'subtitle' => $v['subtitle'] ?? '',
            'visibility' => $v['visibility'],
            'blocks' => $v['blocks'] ?? [],
        ];
    }
}
