<?php

namespace App\Http\Requests\Support;

use App\Domain\Support\Enums\ContactReasonEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', new Enum(ContactReasonEnum::class)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Le motif du message est requis.',
            'name.required' => 'Votre nom est requis.',
            'email.required' => 'Votre e-mail est requis.',
            'email.email' => "L'e-mail n'est pas valide.",
            'message.required' => 'Le message ne peut pas être vide.',
        ];
    }
}
