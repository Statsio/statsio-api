<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LanguageMiddleware
{
    protected LanguageService $languageService;

    public function __construct(LanguageService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifier si une langue est spécifiée dans les paramètres de requête
        $requestedLang = $request->query('lang');

        if ($requestedLang && $this->languageService->isLanguageSupported($requestedLang)) {
            $this->languageService->setLanguage($requestedLang);
        } else {
            // Détecter automatiquement la langue
            $detectedLang = $this->languageService->detectLanguage();
            $this->languageService->setLanguage($detectedLang);
        }

        return $next($request);
    }
}
