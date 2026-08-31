<?php

namespace App\Http\Requests\Channel;

use App\Domain\Channel\Enums\ChannelPermissionEnum;
use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Models\Channel\ChannelUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteChannelMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $channelId = (int) $this->route('id');

        return ChannelUser::userIsOwner($channelId, $user->id)
            || ChannelUser::userHasPermission($channelId, $user->id, 'team.invite_members');
    }

    public function rules(): array
    {
        return [
            'emails' => 'required|array|min:1|max:20',
            'emails.*' => 'required|email|max:255',
            'role' => ['required', Rule::in(array_map(
                fn (ChannelUserRoleEnum $r) => $r->value,
                ChannelUserRoleEnum::getInvitableRoles(),
            ))],
            'permissions' => 'sometimes|array',
            'permissions.*' => [Rule::in(ChannelPermissionEnum::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'emails.required' => 'Au moins une adresse e-mail est requise.',
            'emails.max' => 'Vous pouvez inviter au maximum 20 adresses à la fois.',
            'emails.*.email' => 'Une des adresses e-mail saisies est invalide.',
            'role.required' => 'Le rôle est requis.',
            'role.in' => 'Rôle invalide.',
        ];
    }
}
