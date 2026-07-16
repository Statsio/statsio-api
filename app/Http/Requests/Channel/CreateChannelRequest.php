<?php

namespace App\Http\Requests\Channel;

use App\Domain\Channel\Enums\ChannelCategoryEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'handle' => 'required|string|max:50|regex:/^[a-zA-Z0-9_]+$/|unique:channel_profiles,handle',
            'description' => 'sometimes|string|max:1000',
            'category' => ['sometimes', Rule::in(ChannelCategoryEnum::values())],
            'categories' => 'sometimes|array',
            'categories.*' => ['string', Rule::in(ChannelCategoryEnum::values())],
            'logo' => 'sometimes|file|image:allow_svg|max:5120', // max 5MB
            'banner' => 'sometimes|file|image:allow_svg|max:10240', // max 10MB
            'status' => 'sometimes|in:active,suspended,banned,anonymized',
            'suspended_until' => 'sometimes|date|after:now',
            'anonymized_at' => 'sometimes|date'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis',
            'name.string' => 'Le nom doit être une chaîne de caractères',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères',
            'handle.required' => 'L\'identifiant est requis',
            'handle.string' => 'L\'identifiant doit être une chaîne de caractères',
            'handle.max' => 'L\'identifiant ne doit pas dépasser 50 caractères',
            'handle.regex' => 'L\'identifiant ne peut contenir que des lettres, chiffres et underscores',
            'handle.unique' => 'Cet identifiant est déjà utilisé',
            'description.string' => 'La description doit être une chaîne de caractères',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères',
            'logo.file' => 'Le logo doit être un fichier',
            'logo.image' => 'Le logo doit être une image',
            'logo.max' => 'Le logo ne doit pas dépasser 5MB',
            'banner.file' => 'La bannière doit être un fichier',
            'banner.image' => 'La bannière doit être une image',
            'banner.max' => 'La bannière ne doit pas dépasser 10MB',
            'status.in' => 'Le statut doit être l\'une des valeurs suivantes: active, suspended, banned, anonymized',
            'suspended_until.after' => 'La date de suspension doit être dans le futur',
        ];
    }
}
