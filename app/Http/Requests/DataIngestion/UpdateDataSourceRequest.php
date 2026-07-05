<?php

namespace App\Http\Requests\DataIngestion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSizeKb = (int) config('statsio.data_ingestion.max_file_size_kb', 102400); // 100 Mo par défaut

        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'visibility' => ['sometimes', 'in:private,public'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:50'],
            'provenance_id' => ['sometimes', 'nullable', 'integer', 'exists:source_provenances,id'],
            'provenance_other_label' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Remplacement de fichier (sources de type "upload")
            'file' => ['sometimes', 'file', "max:{$maxSizeKb}", 'extensions:csv,txt,xlsx,xls,json,parquet'],

            // Reconfiguration de la connexion (sources de type "api")
            'url' => ['sometimes', 'url'],
            'method' => ['sometimes', 'in:GET,POST'],
            'auth_type' => ['sometimes', 'in:none,api_key,bearer'],
            'headers' => ['sometimes', 'array'],
            'data_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'refresh_frequency' => ['sometimes', 'nullable', 'in:none,daily,weekly,monthly,yearly'],
        ], CreateApiDataSourceRequest::paginationRules());
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis.',
            'file.max' => 'Le fichier ne doit pas dépasser 100 Mo.',
            'file.extensions' => 'Format non supporté. Formats acceptés : CSV, XLSX, JSON, Parquet.',
            'url.url' => "L'URL n'est pas valide.",
            'provenance_id.exists' => 'Provenance invalide.',
        ];
    }
}
