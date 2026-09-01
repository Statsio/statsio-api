<?php

use App\Console\Commands\MonitorHealthCommand;
use App\Http\Middleware\LanguageMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Laravel exécute chaque tâche planifiée via Symfony Process, qui capture systématiquement
        // la sortie du sous-shell dans son propre pipe (donc `/dev/stdout` s'y reboucle et se fait
        // avaler par le callback muet de execute()) : cibler /proc/1/fd/1 contourne ce pipe et écrit
        // directement dans le flux stdout réel du conteneur (PID 1), celui que Docker capture.
        // Pas de withoutOverlapping() ici : son mutex passe par le cache (Redis) — dépendre de Redis
        // pour surveiller Redis créerait un angle mort exactement quand la sonde doit fonctionner.
        // Inutile de toute façon : l'exécution prend ~0,5s pour un cycle d'une minute.
        $schedule->command(MonitorHealthCommand::class)
            ->everyMinute()
            ->sendOutputTo('/proc/1/fd/1');

        $schedule->command('data-sources:refresh-due')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            HandleCors::class,
            LanguageMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // L'API rend toujours du JSON ; le panneau d'administration Filament (routes web
        // sous /admin) doit garder les réponses HTML de Laravel (redirection invité vers
        // /admin/login, pages 403/419/404 stylées, redirections de validation).
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Journalise en ERROR uniquement les vraies anomalies serveur (5xx), avec le contexte
        // nécessaire pour diagnostiquer sans ouvrir OpenObserve : URL, méthode, utilisateur,
        // type d'exception. Les 4xx (validation, non-authentifié, non-autorisé) sont des cas
        // d'usage normaux du client, pas des anomalies — on ne les journalise pas du tout ici,
        // return false empêchant aussi le report() par défaut de Laravel pour éviter un doublon.
        $exceptions->report(function (Throwable $e): bool {
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException) {
                return false;
            }

            $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;

            if ($statusCode < 500) {
                return false;
            }

            Log::error('Erreur applicative non gérée', [
                'exception_type' => get_class($e),
                'message' => $e->getMessage(),
                'url' => request()?->fullUrl(),
                'method' => request()?->method(),
                'user_id' => auth()->id(),
                'status_code' => $statusCode,
                'location' => $e->getFile().':'.$e->getLine(),
            ]);

            return false;
        });

        // Helper function pour formater les réponses d'erreur API
        $formatApiError = function (string $messageKey, string $errorType, int $statusCode, ?Throwable $e = null) {
            $response = [
                'message' => __($messageKey, ['code' => $statusCode]),
                'error' => $errorType,
            ];

            if (config('app.debug') && $e) {
                $debug = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ];

                // Ajouter file/line/trace pour les erreurs serveur
                if ($statusCode >= 500 || $e instanceof Throwable) {
                    $debug['file'] = $e->getFile();
                    $debug['line'] = $e->getLine();

                    if ($statusCode === 500 && ! ($e instanceof HttpException)) {
                        $debug['trace'] = collect($e->getTrace())->map(function ($trace) {
                            return array_filter($trace, function ($key) {
                                return in_array($key, ['file', 'line', 'function', 'class']);
                            }, ARRAY_FILTER_USE_KEY);
                        })->all();
                    }
                }

                $response['debug'] = $debug;
            }

            return response()->json($response, $statusCode);
        };

        // 404 - Route non trouvée
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                return $formatApiError('errors.route_not_found', 'Route not found', 404, $e);
            }
        });

        // 401 - Non authentifié
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                return $formatApiError('errors.unauthenticated', 'Unauthenticated', 401, $e);
            }
        });

        // 403 - Non autorisé
        $exceptions->render(function (AuthorizationException $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                return $formatApiError('errors.unauthorized', 'Unauthorized', 403, $e);
            }
        });

        // 422 - Erreur de validation
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $response = [
                    'message' => __('errors.validation_failed'),
                    'errors' => $e->errors(),
                ];

                if (config('app.debug')) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ];
                }

                return response()->json($response, 422);
            }
        });

        // 413 - Payload trop volumineux
        $exceptions->render(function (PostTooLargeException $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                return $formatApiError('errors.payload_too_large', 'Payload Too Large', 413, $e);
            }
        });

        // 500 et autres erreurs HTTP
        $exceptions->render(function (HttpException $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                $statusCode = $e->getStatusCode();
                $messageKey = match ($statusCode) {
                    500 => 'errors.server_error',
                    503 => 'errors.service_unavailable',
                    429 => 'errors.too_many_requests',
                    413 => 'errors.payload_too_large',
                    default => 'errors.http_error'
                };

                return $formatApiError($messageKey, 'HTTP Error', $statusCode, $e);
            }
        });

        // Erreurs générales (500)
        $exceptions->render(function (Throwable $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                return $formatApiError('errors.server_error', 'Internal Server Error', 500, $e);
            }
        });
    })->create();
