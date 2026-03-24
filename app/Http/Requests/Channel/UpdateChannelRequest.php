<?php

namespace App\Http\Requests\Channel;

use App\Domain\Channel\Enums\ChannelCategoryEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel_profile_id' => 'sometimes|exists:channel_profiles,id',
            'category' => ['sometimes', Rule::in(ChannelCategoryEnum::values())],
            'categories' => 'sometimes|array',
            'categories.*' => ['string', Rule::in(ChannelCategoryEnum::values())],
            'status' => 'sometimes|in:active,suspended,banned,anonymized',
            'suspended_until' => 'sometimes|date|after:now',
            'anonymized_at' => 'sometimes|date'
        ];
    }

    public function messages(): array
    {
        return [
            'channel_profile_id.exists' => 'Le profil du channel n\'existe pas',
            'status.in' => 'Le statut doit être l\'une des valeurs suivantes: active, suspended, banned, anonymized',
            'suspended_until.after' => 'La date de suspension doit être dans le futur',
        ];
    }
}
