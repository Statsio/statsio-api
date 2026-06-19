<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StatsDataSourceController extends Controller
{
    /**
     * Test de connexion pour une future source de type API
     */
    public function probeConnection(Request $request)
    {
        // TODO: Implement probe connection logic
        return response()->json([
            'success' => true,
            'message' => 'Connection probe not yet implemented'
        ]);
    }
}
