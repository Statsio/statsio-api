<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//controllers
use App\Http\Controllers\Api\HealthCheckController;

Route::get('/healthcheck', [HealthCheckController::class, 'check']);

?>