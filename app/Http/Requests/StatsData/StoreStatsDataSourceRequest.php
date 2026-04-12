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
            'verify' => 'sometimes|boolean',
            'responseRoot' => 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * @return array{type: string, name: ?string, manual_data: array, api_url: ?string, api_key: ?string, verify: bool, response_root: ?string}
     */
    public function normalizedPayload(): array
    {
        $v = $this->validated();

        return [
            'type' => $v['type'],
            'name' => $v['name'] ?? null,
            'manual_data' => $v['manualData'] ?? [],
            'api_url' => $v['apiUrl'] ?? null,
            'api_key' => $v['apiKey'] ?? null,
            'verify' => (bool) ($v['verify'] ?? false),
            'response_root' => $v['responseRoot'] ?? null,
        ];
    }
}
