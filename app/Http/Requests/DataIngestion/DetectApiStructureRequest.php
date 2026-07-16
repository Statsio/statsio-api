<?php

namespace App\Http\Requests\DataIngestion;

use Illuminate\Foundation\Http\FormRequest;

class DetectApiStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url'],
            'headers' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'L\'URL est requise.',
            'url.url' => 'L\'URL n\'est pas valide.',
        ];
    }
}
