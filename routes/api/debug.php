<?php

use Illuminate\Support\Facades\Route;

// Route temporaire pour valider de bout en bout la chaîne d'alerte "erreurs applicatives"
// (cf. Bloc 4, 1.2/1.3/2.2) : déclenche volontairement une exception non attrapée, exactement
// comme un vrai bug en production, pour vérifier que l'alerte statsio_app_errors se déclenche
// et que le message Slack contient bien l'URL, la méthode et l'utilisateur (si connecté).
//
// À SUPPRIMER une fois le test effectué : laissé en place, n'importe qui pourrait générer un
// log ERROR à volonté, ce qui n'a pas vocation à rester exposé indéfiniment.
Route::get('/debug/trigger-error', function () {
    throw new \RuntimeException('Erreur de test — déclenchée volontairement pour valider la chaîne d\'alerte.');
});
