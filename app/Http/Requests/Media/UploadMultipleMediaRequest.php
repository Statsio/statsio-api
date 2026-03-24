<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class UploadMultipleMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => 'required|array|max:10',
            'files.*' => 'required|file|max:10240',
            'directory' => 'sometimes|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Les fichiers sont requis',
            'files.array' => 'Le champ fichiers doit etre un tableau',
            'files.max' => 'Vous ne pouvez pas envoyer plus de 10 fichiers',
            'files.*.required' => 'Chaque fichier est requis',
            'files.*.file' => 'Chaque element doit etre un fichier',
            'files.*.max' => 'Chaque fichier ne doit pas depasser 10MB',
            'directory.string' => 'Le repertoire doit etre une chaine de caracteres',
            'directory.max' => 'Le nom du repertoire ne doit pas depasser 255 caracteres',
        ];
    }
}
