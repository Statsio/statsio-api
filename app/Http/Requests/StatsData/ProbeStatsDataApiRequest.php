<?php

namespace App\Http\Requests\StatsData;

use Illuminate\Foundation\Http\FormRequest;

class ProbeStatsDataApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('api_key') && ! $this->has('apiKey')) {
            $this->merge(['apiKey' => $this->input('api_key')]);
        }
    }

    public function rules(): array
    {
        return [
            'url' => 'required|url|max:2048',
            'apiKey' => 'nullable|string|max:2000',
        ];
    }
}
