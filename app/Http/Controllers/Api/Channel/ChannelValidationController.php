<?php

namespace App\Http\Controllers\Api\Channel;

use App\Http\Controllers\Controller;
use App\Models\Channel\ChannelProfile;
use Illuminate\Http\Request;

class ChannelValidationController extends Controller
{
    /**
     * Vérifie si un handle est disponible
     */
    public function checkHandle(string $handle)
    {
        $exists = ChannelProfile::where('handle', $handle)->exists();

        return response()->json([
            'available' => !$exists,
            'handle' => $handle
        ]);
    }
}
