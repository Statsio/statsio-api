<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageService
{
    /**
     * Langues supportées
     */
    const SUPPORTED_LANGUAGES = ['fr', 'en'];

    /**
     * Langue par défaut
     */
    const DEFAULT_LANGUAGE = 'fr';

    /**
     * Détecte la langue préférée de l'utilisateur
     */
    public function detectLanguage(): string
    {
        // 1. Vérifier si une langue est définie en session
        $sessionLang = Session::get('locale');
        if ($sessionLang && in_array($sessionLang, self::SUPPORTED_LANGUAGES)) {
            return $sessionLang;
        }

        // 2. Vérifier l'en-tête Accept-Language
        $browserLang = $this->getBrowserLanguage();
        if ($browserLang && in_array($browserLang, self::SUPPORTED_LANGUAGES)) {
            return $browserLang;
        }

        // 3. Retourner la langue par défaut
        return self::DEFAULT_LANGUAGE;
    }

    /**
     * Définit la langue de l'application
     */
    public function setLanguage(string $locale): bool
    {
        if (!in_array($locale, self::SUPPORTED_LANGUAGES)) {
            return false;
        }

        App::setLocale($locale);
        Session::put('locale', $locale);

        return true;
    }

    /**
     * Obtient la langue actuelle
     */
    public function getCurrentLanguage(): string
    {
        return App::getLocale();
    }

    /**
     * Obtient toutes les langues supportées
     */
    public function getSupportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /**
     * Traduit un message
     */
    public function translate(string $key, array $replace = [], ?string $locale = null): string
    {
        return trans($key, $replace, $locale);
    }

    /**
     * Vérifie si une langue est supportée
     */
    public function isLanguageSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LANGUAGES);
    }

    /**
     * Obtient la langue du navigateur
     */
    private function getBrowserLanguage(): ?string
    {
        $acceptLanguage = request()->header('Accept-Language');

        if (!$acceptLanguage) {
            return null;
        }

        // Extraire la première langue préférée
        $languages = explode(',', $acceptLanguage);
        $primaryLanguage = trim(explode(';', $languages[0])[0]);

        // Retourner seulement la partie principale (ex: 'fr' au lieu de 'fr-FR')
        return explode('-', $primaryLanguage)[0];
    }
}
