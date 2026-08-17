<?php

namespace App\Http\Requests\Channel;

use App\Models\Channel\ChannelUser;
use Illuminate\Foundation\Http\FormRequest;

class DeleteChannelRequest extends FormRequest
{
    /**
     * Seul un propriétaire peut supprimer la chaîne — ce n'est pas une
     * permission assignable (voir ChannelPermissionEnum), c'est un contrôle
     * de rôle en dur.
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
        return [];
    }
}
