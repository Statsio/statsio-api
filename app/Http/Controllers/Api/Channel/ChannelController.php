<?php

namespace App\Http\Controllers\Api\Channel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domain\Channel\Actions\ChannelAction;
use App\Models\Channel\ChannelProfile;
use App\Http\Requests\Channel\CreateChannelRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;

class ChannelController extends Controller
{
    public function __construct(
        private ChannelAction $channelAction
    ) {}

    /**
     * Crée un channel
     */
    public function create(CreateChannelRequest $request)
    {
        $data = $request->validated();
        $channel = $this->channelAction->createChannel($data);

        return response()->json([
            'success' => true,
            'message' => __('channel.created_successfully'),
            'data' => $channel
        ], 201);
    }

    /**
     * Affiche un channel spécifique
     */
    public function show(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $channel
        ]);
    }

    /**
     * Met à jour un channel
     */
    public function update(UpdateChannelRequest $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $data = $request->validated();
        $channel = $this->channelAction->updateChannelProfile($channel, $data);

        return response()->json([
            'success' => true,
            'message' => __('channel.updated_successfully'),
            'data' => $channel
        ]);
    }

    /**
     * Supprime un channel
     */
    public function destroy(int $id)
    {
        $channelProfile = $this->channelAction->getChannelById($id);

        if (!$channelProfile) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $this->channelAction->deleteChannel($channelProfile->channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.deleted_successfully')
        ]);
    }

    /**
     * Liste tous les channels
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $channels = $this->channelAction->getAllChannels($perPage);

        return response()->json([
            'success' => true,
            'data' => $channels
        ]);
    }

    /**
     * Suspend un channel
     */
    public function suspend(Request $request, int $id)
    {
        $channelProfile = $this->channelAction->getChannelById($id);

        if (!$channelProfile) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $until = $request->get('suspended_until') ? new \DateTime($request->get('suspended_until')) : null;
        $channel = $this->channelAction->suspendChannel($channelProfile->channel, $until);

        return response()->json([
            'success' => true,
            'message' => __('channel.suspended_successfully'),
            'data' => $channel
        ]);
    }

    /**
     * Bannit un channel
     */
    public function ban(int $id)
    {
        $channelProfile = $this->channelAction->getChannelById($id);

        if (!$channelProfile) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $channel = $this->channelAction->banChannel($channelProfile->channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.banned_successfully'),
            'data' => $channel
        ]);
    }

    /**
     * Active un channel
     */
    public function activate(int $id)
    {
        $channelProfile = $this->channelAction->getChannelById($id);

        if (!$channelProfile) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $channel = $this->channelAction->activateChannel($channelProfile->channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.activated_successfully'),
            'data' => $channel
        ]);
    }

    /**
     * Anonymise un channel
     */
    public function anonymize(int $id)
    {
        $channelProfile = $this->channelAction->getChannelById($id);

        if (!$channelProfile) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $channel = $this->channelAction->anonymizeChannel($channelProfile->channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.anonymized_successfully'),
            'data' => $channel
        ]);
    }
}
