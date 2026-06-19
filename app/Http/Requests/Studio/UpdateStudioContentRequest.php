<?php

namespace App\Http\Requests\Studio;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudioContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'blocks' => ['required', 'array'],
            'blocks.*.id' => ['required', 'string'],
            'blocks.*.type' => ['required', 'string', 'in:heading,paragraph,layout-2col,layout-3col,table,chart-line,chart-bar,chart-pie'],
            'blocks.*.content' => ['required', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'blocks.required' => 'Les blocks sont obligatoires.',
            'blocks.array' => 'Les blocks doivent être un tableau.',
            'blocks.*.type.in' => 'Le type de block est invalide.',
        ];
    }
}
