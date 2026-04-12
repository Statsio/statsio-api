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
        if ($this->has('sort_order') && ! $this->has('sortOrder')) {
            $merge['sortOrder'] = $this->input('sort_order');
        }
        if ($this->has('response_root') && ! $this->has('responseRoot')) {
            $merge['responseRoot'] = $this->input('response_root');
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
            'verify' => 'sometimes|boolean',
            'responseRoot' => 'sometimes|nullable|string|max:255',
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
        if (array_key_exists('verify', $v)) {
            $out['verify'] = (bool) $v['verify'];
        }
        if (array_key_exists('responseRoot', $v)) {
            $out['response_root'] = $v['responseRoot'];
        }

        return $out;
    }
}
