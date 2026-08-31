<?php

namespace App\Http\Requests\Channel;

use App\Models\Channel\ChannelUser;
use App\Models\Channel\Organization;
use Illuminate\Foundation\Http\FormRequest;

class LinkOrganizationRequest extends FormRequest
{
    /**
     * Lier une chaîne à une organisation existante nécessite d'être owner des
     * deux côtés : la chaîne qu'on lie, et la chaîne principale de
     * l'organisation ciblée. Dans la pratique, ça restreint la liaison aux
     * utilisateurs qui possèdent déjà les deux chaînes — voir
     * OrganizationAction::joinableFor(), qui ne propose que ces organisations-là.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $channelId = (int) $this->route('id');
        if (! ChannelUser::userIsOwner($channelId, $user->id)) {
            return false;
        }

        $organization = Organization::find($this->input('organization_id'));
        if (! $organization) {
            return false;
        }

        return ChannelUser::userIsOwner($organization->principal_channel_id, $user->id);
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
        ];
    }
}
