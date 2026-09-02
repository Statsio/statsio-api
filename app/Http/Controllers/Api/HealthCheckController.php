<?php

namespace App\Http\Controllers\Api;

use App\Domain\HealthCheck\Actions\CheckHealthAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function check(CheckHealthAction $action): JsonResponse
    {
        return response()->json(
            $action->execute()
        );
    }
}
