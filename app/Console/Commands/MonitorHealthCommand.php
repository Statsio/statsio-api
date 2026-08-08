<?php

namespace App\Console\Commands;

use App\Domain\HealthCheck\Actions\CheckHealthAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Exécutée toutes les minutes par le scheduler (cf. bootstrap/app.php). Journalise le résultat
 * de CheckHealthAction en `critical` en cas d'échec, en `info` sinon : ce flux de logs est déjà
 * collecté par Vector puis expédié vers OpenObserve (stream `docker-logs`), ce qui permet d'y
 * définir une règle d'alerte sur le niveau `critical` sans dépendre d'un outillage supplémentaire.
 * Le log `info` régulier sert aussi de "signal de vie" : son absence prolongée dans OpenObserve
 * trahit un arrêt total du process (scheduler mort), que le check `critical` seul ne peut pas détecter.
 */
class MonitorHealthCommand extends Command
{
    protected $signature = 'health:monitor';

    protected $description = 'Vérifie l\'état de santé de l\'application et journalise le résultat pour la supervision';

    public function handle(CheckHealthAction $action): int
    {
        $result = $action->execute();

        if (in_array(false, $result['checks'], true)) {
            Log::critical('Health check failed', $result);
            $this->error('Health check failed: '.json_encode($result['checks']));

            return self::FAILURE;
        }

        Log::info('Health check OK', $result);
        $this->info('Health check OK');

        return self::SUCCESS;
    }
}
