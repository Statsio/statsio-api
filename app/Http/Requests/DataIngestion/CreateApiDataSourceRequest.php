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
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'method' => ['sometimes', 'string', 'in:GET,POST'],
            'auth_type' => ['sometimes', 'string', 'in:none,api_key,bearer'],
            'headers' => ['sometimes', 'array'],
            'data_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'refresh_frequency' => ['sometimes', 'nullable', 'in:none,daily,weekly,monthly,yearly'],
            'visibility' => ['sometimes', 'nullable', 'in:private,public'],
            'categories' => ['sometimes', 'nullable', 'array'],
            'categories.*' => ['string', 'max:50'],
            'provenance_id' => ['sometimes', 'nullable', 'integer', 'exists:source_provenances,id'],
            'provenance_other_label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], $this->paginationRules());
    }

    /**
     * Règles de validation pour la pagination d'une source API, partagées avec
     * UpdateDataSourceRequest — bornées par la config `data_ingestion.pagination`.
     */
    public static function paginationRules(): array
    {
        $maxPagesHardCap = (int) config('statsio.data_ingestion.pagination.max_pages_hard_cap', 500);

        return [
            'pagination' => ['sometimes', 'nullable', 'array'],
            'pagination.style' => ['required_with:pagination', 'in:none,offset,page,cursor,next_link'],
            'pagination.param_name' => ['required_if:pagination.style,offset,page', 'nullable', 'string', 'max:100'],
            'pagination.param_start' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'pagination.size_param' => ['sometimes', 'nullable', 'string', 'max:100'],
            'pagination.page_size' => ['required_if:pagination.style,offset,page,cursor', 'nullable', 'integer', 'min:1', 'max:1000'],
            'pagination.total_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pagination.total_mode' => ['sometimes', 'nullable', 'in:items,pages'],
            'pagination.cursor_param' => ['required_if:pagination.style,cursor', 'nullable', 'string', 'max:100'],
            'pagination.cursor_path' => ['required_if:pagination.style,cursor', 'nullable', 'string', 'max:255'],
            'pagination.next_link_source' => ['required_if:pagination.style,next_link', 'nullable', 'in:body,header'],
            'pagination.next_link_path' => ['required_if:pagination.next_link_source,body', 'nullable', 'string', 'max:255'],
            'pagination.max_pages' => ['sometimes', 'nullable', 'integer', 'min:1', "max:{$maxPagesHardCap}"],
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
