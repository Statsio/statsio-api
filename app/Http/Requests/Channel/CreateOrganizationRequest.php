<?php

namespace App\Http\Requests\Channel;

use App\Models\Channel\ChannelUser;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
{
    /**
     * Seul le propriétaire de la chaîne peut créer une organisation depuis
     * celle-ci (elle en devient la chaîne principale).
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return ChannelUser::userIsOwner((int) $this->route('id'), $user->id);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}
