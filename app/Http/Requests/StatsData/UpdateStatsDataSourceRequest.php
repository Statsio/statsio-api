<?php

namespace App\Http\Requests\StatsData;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatsDataSourceRequest extends FormRequest
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
        if ($this->has('sort_order') && ! $this->has('sortOrder')) {
            $merge['sortOrder'] = $this->input('sort_order');
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
            'name' => 'sometimes|nullable|string|max:255',
            'sortOrder' => 'sometimes|integer|min:0',
            'manualData' => 'sometimes|array',
            'file' => [
                'sometimes',
                'file',
                'max:20480',
                'mimes:csv,txt,xlsx,xls',
            ],
            'apiUrl' => 'sometimes|url|max:2048',
            'apiKey' => 'sometimes|nullable|string|max:2000',
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
        $out = [];
        if (array_key_exists('name', $v)) {
            $out['name'] = $v['name'];
        }
        if (array_key_exists('sortOrder', $v)) {
            $out['sort_order'] = $v['sortOrder'];
        }
        if (array_key_exists('manualData', $v)) {
            $out['manual_data'] = $v['manualData'];
        }
        if (array_key_exists('apiUrl', $v)) {
            $out['api_url'] = $v['apiUrl'];
        }
        if (array_key_exists('apiKey', $v)) {
            $out['api_key'] = $v['apiKey'];
        }
        if (array_key_exists('apiLimit', $v)) {
            $out['api_limit'] = $v['apiLimit'];
        }
        if (array_key_exists('apiSearchTemplate', $v)) {
            $out['api_search_template'] = $v['apiSearchTemplate'];
        }
        if (array_key_exists('apiSearchField', $v)) {
            $out['api_search_field'] = $v['apiSearchField'];
        }
        if (array_key_exists('verify', $v)) {
            $out['verify'] = (bool) $v['verify'];
        }
        if (array_key_exists('responseRoot', $v)) {
            $out['response_root'] = $v['responseRoot'];
        }
        if (array_key_exists('normalizationMapping', $v)) {
            $out['normalization_mapping'] = $v['normalizationMapping'];
        }

        return $out;
    }
}
