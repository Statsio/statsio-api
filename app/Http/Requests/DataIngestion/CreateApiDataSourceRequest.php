<?php

namespace App\Http\Requests\DataIngestion;

use Illuminate\Foundation\Http\FormRequest;

class CreateApiDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'method' => ['sometimes', 'string', 'in:GET,POST'],
            'auth_type' => ['sometimes', 'string', 'in:none,api_key,bearer'],
            'headers' => ['sometimes', 'array'],
            'data_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'nullable', 'in:private,public'],
            'categories' => ['sometimes', 'nullable', 'array'],
            'categories.*' => ['string', 'max:50'],
            'provenance_id' => ['sometimes', 'nullable', 'integer', 'exists:source_provenances,id'],
            'provenance_other_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis.',
            'url.required' => 'L\'URL est requise.',
            'url.url' => 'L\'URL n\'est pas valide.',
            'provenance_id.exists' => 'Provenance invalide.',
        ];
    }
}
