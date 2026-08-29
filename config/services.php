<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'turnstile' => [
        'secret' => env('TURNSTILE_SECRET'),
    ],

    'duckdb' => [
        'path' => env('DUCKDB_PATH', '/usr/local/bin/duckdb'),
    ],

    'medicaments_api' => [
        'base_url' => env('MEDICAMENTS_API_BASE_URL', 'https://medicaments-api.giygas.dev'),
    ],

    'who_gho_api' => [
        'base_url' => env('WHO_GHO_API_BASE_URL', 'https://ghoapi.azureedge.net/api'),
    ],

    'icd11_api' => [
        'base_url' => env('ICD11_API_BASE_URL', 'https://id.who.int'),
        'token_url' => env('ICD11_TOKEN_URL', 'https://icdaccessmanagement.who.int/connect/token'),
        'client_id' => env('ICD11_CLIENT_ID'),
        'client_secret' => env('ICD11_CLIENT_SECRET'),
        'release_id' => env('ICD11_RELEASE_ID', 'latest'),
    ],

    'umls_api' => [
        'base_url' => env('UMLS_API_BASE_URL', 'https://uts-ws.nlm.nih.gov/rest'),
        'key' => env('UMLS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assistant IA du Studio
    |--------------------------------------------------------------------------
    |
    | Le driver est câblé derrière l'interface App\Services\Ai\LlmClient : changer
    | de fournisseur (Gemini → Mistral, Groq, Ollama…) se fait par variable d'env,
    | sans toucher au domaine App\Domain\Ai.
    |
    */
    'ai' => [
        'driver' => env('AI_DRIVER', 'gemini'),

        // Boucle d'agent : plafonds partagés par tous les drivers.
        'max_iterations' => (int) env('AI_MAX_ITERATIONS', 15),
        'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 10),

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            // Alias « lite » : quota gratuit le plus large + function calling correct.
            // Passer à gemini-3.6-flash / gemini-flash-latest pour plus de finesse.
            'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => (int) env('GEMINI_TIMEOUT', 45),
            // Gemini 3.x « pense » avant de répondre et consomme ce budget — le garder large.
            'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 8192),
            // low | high (Gemini 3.x). Vide pour ne pas envoyer thinkingConfig (modèles 2.x).
            'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
        ],
    ],

];
