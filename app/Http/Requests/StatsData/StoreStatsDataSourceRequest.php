<?php

namespace App\Http\Requests\StatsData;

use App\Domain\StatsData\Enums\StatsDataSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatsDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('manual_data') && ! $this->has('manualData')) {
            $merge['manualData'] = $this->input('manual_data');
        }
        if ($this->has('api_url') && ! $this->has('apiUrl')) {
            $merge['apiUrl'] = $this->input('api_url');
        }
        if ($this->has('api_key') && ! $this->has('apiKey')) {
            $merge['apiKey'] = $this->input('api_key');
        }
        if ($this->has('api_search_template') && ! $this->has('apiSearchTemplate')) {
            $merge['apiSearchTemplate'] = $this->input('api_search_template');
        }
        if ($this->has('api_search_field') && ! $this->has('apiSearchField')) {
            $merge['apiSearchField'] = $this->input('api_search_field');
        }
        if ($this->has('response_root') && ! $this->has('responseRoot')) {
            $merge['responseRoot'] = $this->input('response_root');
        }
        if ($this->has('normalization_mapping') && ! $this->has('normalizationMapping')) {
            $merge['normalizationMapping'] = $this->input('normalization_mapping');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(StatsDataSourceType::values())],
            'name' => 'nullable|string|max:255',
            'manualData' => 'required_if:type,manual|array',
            'file' => [
                'required_if:type,file',
                'file',
                'max:20480',
                'mimes:csv,txt,xlsx,xls',
            ],
            'apiUrl' => 'required_if:type,api|nullable|url|max:2048',
            'apiKey' => 'nullable|string|max:2000',
            'apiLimit' => 'sometimes|nullable|integer|min:1|max:50000',
            'apiSearchTemplate' => 'sometimes|nullable|string|max:4000',
            'apiSearchField' => 'sometimes|nullable|string|max:255',
            'verify' => 'sometimes|boolean',
            'responseRoot' => 'sometimes|nullable|string|max:255',
            'normalizationMapping' => 'sometimes|nullable|array',
            'normalizationMapping.rowPath' => 'sometimes|nullable|string|max:512',
            'normalizationMapping.keyFields' => 'sometimes|array',
            'normalizationMapping.keyFields.*.name' => 'required|string|max:255',
            'normalizationMapping.keyFields.*.from' => 'nullable|string|max:512',
            'normalizationMapping.valueFields' => 'sometimes|array',
            'normalizationMapping.valueFields.*.name' => 'required|string|max:255',
            'normalizationMapping.valueFields.*.from' => 'nullable|string|max:512',
            'normalizationMapping.staticKeys' => 'sometimes|array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedPayload(): array
    {
        $v = $this->validated();

        $out = [
            'type' => $v['type'],
            'name' => $v['name'] ?? null,
            'manual_data' => $v['manualData'] ?? [],
            'api_url' => $v['apiUrl'] ?? null,
            'api_key' => $v['apiKey'] ?? null,
            'api_limit' => array_key_exists('apiLimit', $v) ? $v['apiLimit'] : null,
            'api_search_template' => array_key_exists('apiSearchTemplate', $v) ? $v['apiSearchTemplate'] : null,
            'api_search_field' => array_key_exists('apiSearchField', $v) ? $v['apiSearchField'] : null,
            'verify' => (bool) ($v['verify'] ?? false),
            'response_root' => $v['responseRoot'] ?? null,
        ];
        if (array_key_exists('normalizationMapping', $v)) {
            $out['normalization_mapping'] = $v['normalizationMapping'];
        }

        return $out;
    }
}
