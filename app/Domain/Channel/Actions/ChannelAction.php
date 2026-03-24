<?php

namespace App\Domain\Channel\Actions;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelProfile;
use App\Domain\Channel\Enums\ChannelStatusEnum;
use App\Domain\Channel\Actions\ChannelProfileAction;
use Illuminate\Http\UploadedFile;

class ChannelAction
{
    public function __construct(
        private ChannelProfileAction $profileAction
    ) {}

    public function createChannel(array $data): ChannelProfile
    {
        // Créer le channel sans profil d'abord
        $channel = Channel::create([
            'status' => $data['status'] ?? 'active',
            'suspended_until' => $data['suspended_until'] ?? null,
            'anonymized_at' => $data['anonymized_at'] ?? null,
        ]);

        // Ajouter l'ID du channel aux données
        $data['channel_id'] = $channel->id;

        // Créer le profil avec le ChannelProfileAction
        return $this->profileAction->createProfile($data);
    }

    public function updateChannel(Channel $channel, array $data): Channel
    {
        $channel->update($data);
        return $channel;
    }

    public function updateChannelProfile(ChannelProfile $profile, array $data): ChannelProfile
    {
        return $this->profileAction->updateProfile($profile, $data);
    }

    public function deleteChannel(Channel $channel): bool
    {
        return $channel->delete();
    }

    public function getChannelById(int $id): ?ChannelProfile
    {
        return Channel::with('profile')->find($id)?->profile;
    }

    public function getChannelProfileById(int $id): ?ChannelProfile
    {
        return $this->profileAction->getProfileById($id);
    }

    public function getAllChannels(int $perPage = 15)
    {
        return Channel::with('profile')->paginate($perPage);
    }

    public function suspendChannel(Channel $channel): Channel
    {
        $channel->update([
            'status' => ChannelStatusEnum::SUSPENDED->value,
            'suspended_until' => now()->addDays(7)
        ]);

        return $channel;
    }

    public function banChannel(Channel $channel): Channel
    {
        $channel->update([
            'status' => ChannelStatusEnum::BANNED->value,
            'suspended_until' => null
        ]);

        return $channel;
    }

    public function activateChannel(Channel $channel): Channel
    {
        $channel->update([
            'status' => ChannelStatusEnum::ACTIVE->value,
            'suspended_until' => null
        ]);

        return $channel;
    }

    public function anonymizeChannel(Channel $channel): Channel
    {
        $channel->update([
            'status' => ChannelStatusEnum::ANONYMIZED->value,
            'anonymized_at' => now()
        ]);

        return $channel;
    }
}
