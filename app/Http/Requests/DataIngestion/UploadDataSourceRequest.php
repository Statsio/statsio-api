<?php

namespace App\Http\Requests\DataIngestion;

use Illuminate\Foundation\Http\FormRequest;

class UploadDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSizeKb = (int) config('statsio.data_ingestion.max_file_size_kb', 102400); // 100 Mo par défaut

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxSizeKb}",
                'extensions:csv,txt,xlsx,xls,json,parquet',
            ],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visibility' => ['sometimes', 'nullable', 'in:private,public'],
            'categories' => ['sometimes', 'nullable', 'array'],
            'categories.*' => ['string', 'max:50'],
            'provenance_id' => ['sometimes', 'nullable', 'integer', 'exists:source_provenances,id'],
            'provenance_other_label' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Fichiers xlsx/xls uniquement — feuille et ligne d'en-têtes choisies via l'aperçu.
            'sheet_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'header_row' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'excluded_rows' => ['sometimes', 'nullable', 'array'],
            'excluded_rows.*' => ['integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Un fichier est requis.',
            'file.file' => 'Le champ doit être un fichier.',
            'file.max' => 'Le fichier ne doit pas dépasser 100 Mo.',
            'file.extensions' => 'Format non supporté. Formats acceptés : CSV, XLSX, JSON, Parquet.',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'provenance_id.exists' => 'Provenance invalide.',
        ];
    }
}
