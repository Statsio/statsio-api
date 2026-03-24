<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240', // max 10MB
            'directory' => 'sometimes|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Le fichier est requis',
            'file.file' => 'Le champ doit être un fichier',
            'file.max' => 'Le fichier ne doit pas dépasser 10MB',
            'directory.string' => 'Le répertoire doit être une chaîne de caractères',
            'directory.max' => 'Le nom du répertoire ne doit pas dépasser 255 caractères',
        ];
    }
}
