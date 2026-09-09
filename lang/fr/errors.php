<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Error Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for error messages throughout
    | the application. You are free to change these to better suit your
    | application's needs.
    |
    */

    'validation_failed' => 'Les données fournies sont invalides.',
    'invalid_credentials' => 'Identifiants invalides.',

    // Premium
    'premium_blocks_required' => 'Ce contenu utilise des blocs réservés à l\'offre Premium (:blocks). Passez à Premium pour les utiliser.',
    'premium_blocks_locked' => 'Ce contenu contient des blocs Premium déjà en place (:blocks) : ils restent affichés mais ne peuvent plus être ajoutés ni modifiés sans l\'offre Premium.',
    'premium_channel_limit' => 'Votre offre est limitée à :max chaîne(s) éditoriale(s). Passez à une offre supérieure pour en créer davantage.',
    'premium_channel_members_limit' => 'Votre offre est limitée à :max membre(s) par chaîne. Passez à une offre supérieure pour inviter plus de membres.',
    'premium_identity_verification' => 'La vérification d\'identité des votants est réservée à l\'offre Premium.',

    // Billing (Stripe)
    'stripe_not_configured' => 'Le paiement en ligne n\'est pas disponible pour le moment.',

    // HTTP errors
    'route_not_found' => 'L\'URL demandée n\'existe pas.',
    'unauthenticated' => 'Non authentifié.',
    'unauthorized' => 'Non autorisé.',
    'server_error' => 'Erreur interne du serveur.',
    'service_unavailable' => 'Service temporairement indisponible.',
    'too_many_requests' => 'Trop de requêtes. Veuillez réessayer plus tard.',
    'payload_too_large' => 'Le fichier envoye est trop volumineux.',
    'http_error' => 'Erreur HTTP :code',

];
