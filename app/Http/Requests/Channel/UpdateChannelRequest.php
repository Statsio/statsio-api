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
        $channelId = $this->route('id');

        return [
            'name'                   => 'sometimes|string|max:255',
            'handle'                 => ['sometimes', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('channel_profiles', 'handle')->ignore($channelId, 'channel_id')],
            'description'            => 'sometimes|nullable|string|max:1000',
            'category'               => ['sometimes', Rule::in(ChannelCategoryEnum::values())],
            'categories'             => 'sometimes|array',
            'categories.*'           => ['string', Rule::in(ChannelCategoryEnum::values())],
            'logo'                   => 'sometimes|file|image:allow_svg|max:5120',
            'banner'                 => 'sometimes|file|image:allow_svg|max:10240',
            'custom_color_primary'   => 'sometimes|nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'custom_color_secondary' => 'sometimes|nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'age_restriction'        => 'sometimes|integer|in:0,13,16,18',
            'country'                => 'sometimes|nullable|string|max:2',
            'tags'                   => 'sometimes|array',
            'tags.*'                 => 'string|max:50',
            'status'                 => 'sometimes|in:active,suspended,banned,anonymized',
            'suspended_until'        => 'sometimes|date|after:now',
            'anonymized_at'          => 'sometimes|date',
        ];
    }

    public function messages(): array
    {
        return [
            'handle.unique'                  => 'Cet identifiant est déjà utilisé',
            'handle.regex'                   => 'L\'identifiant ne peut contenir que des lettres, chiffres et underscores',
            'custom_color_primary.regex'     => 'La couleur principale doit être au format hexadécimal (#rrggbb)',
            'custom_color_secondary.regex'   => 'La couleur secondaire doit être au format hexadécimal (#rrggbb)',
            'status.in'                      => 'Statut invalide',
            'suspended_until.after'          => 'La date de suspension doit être dans le futur',
        ];
    }
}
