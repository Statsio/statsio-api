<?php

namespace App\Http\Requests\StatsData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertStatsDataDocumentShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email:rfc,dns|max:255',
            'role' => ['required', Rule::in(['viewer', 'editor'])],
        ];
    }

    /**
     * @return array{email: string, role: string}
     */
    public function normalizedPayload(): array
    {
        $v = $this->validated();
        return [
            'email' => (string) $v['email'],
            'role' => (string) $v['role'],
        ];
    }
}

