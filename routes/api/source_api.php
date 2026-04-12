<?php

use App\Http\Controllers\Api\StatsData\StatsDataSourceController;
use Illuminate\Support\Facades\Route;

/*
| Test de connexion pour une future source de type API (URL + clé optionnelle).
| Pas lié à un document StatsData.
*/
Route::middleware('auth:api')->prefix('source-api')->name('source-api.')->group(function () {
    Route::post('/probe-connection', [StatsDataSourceController::class, 'probeConnection'])
        ->name('probe-connection');
});
