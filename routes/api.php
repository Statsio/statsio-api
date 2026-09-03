<?php

require __DIR__.'/api/healthcheck.php';
require __DIR__.'/api/auth.php';
require __DIR__.'/api/user.php';
require __DIR__.'/api/channel.php';
require __DIR__.'/api/source_api.php';
require __DIR__.'/api/media.php';
require __DIR__.'/api/studio.php';
require __DIR__.'/api/ai.php';
require __DIR__.'/api/data_ingestion.php';
require __DIR__.'/api/tv.php';
require __DIR__.'/api/medicaments.php';
require __DIR__.'/api/maladies.php';
require __DIR__.'/api/pays.php';
require __DIR__.'/api/soins.php';
// Le back-office admin est désormais servi par Filament (routes web /admin),
// voir app/Providers/Filament/AdminPanelProvider.php.
require __DIR__.'/api/content.php';
require __DIR__.'/api/dossier.php';
require __DIR__.'/api/contact.php';
require __DIR__.'/api/identity.php';
require __DIR__.'/api/debug.php';
