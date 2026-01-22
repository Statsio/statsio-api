<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->use([
            \App\Http\Middleware\LanguageMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function () {
            return true;
        });

        // Helper function pour formater les réponses d'erreur API
        $formatApiError = function (string $messageKey, string $errorType, int $statusCode, ?Throwable $e = null) use ($exceptions) {
            $response = [
                'message' => __($messageKey, ['code' => $statusCode]),
                'error' => $errorType
            ];

            if (config('app.debug') && $e) {
                $debug = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage()
                ];

                // Ajouter file/line/trace pour les erreurs serveur
                if ($statusCode >= 500 || $e instanceof Throwable) {
                    $debug['file'] = $e->getFile();
                    $debug['line'] = $e->getLine();

                    if ($statusCode === 500 && !($e instanceof HttpException)) {
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
        $exceptions->render(function (ValidationException $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                $response = [
                    'message' => __('errors.validation_failed'),
                    'errors' => $e->errors()
                ];

                if (config('app.debug')) {
                    $response['debug'] = [
                        'exception' => get_class($e),
                        'message' => $e->getMessage()
                    ];
                }

                return response()->json($response, 422);
            }
        });

        // 500 et autres erreurs HTTP
        $exceptions->render(function (HttpException $e, Request $request) use ($formatApiError) {
            if ($request->is('api/*')) {
                $statusCode = $e->getStatusCode();
                $messageKey = match($statusCode) {
                    500 => 'errors.server_error',
                    503 => 'errors.service_unavailable',
                    429 => 'errors.too_many_requests',
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
