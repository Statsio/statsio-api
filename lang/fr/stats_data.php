<?php

return [
    'created' => 'Document StatsData créé.',
    'updated' => 'Document StatsData mis à jour.',
    'not_found' => 'Document introuvable.',
    'source_created' => 'Source de données créée.',
    'source_updated' => 'Source de données mise à jour.',
    'source_not_found' => 'Source de données introuvable.',
    'source_invalid_api_url' => 'URL d’API invalide.',
    'source_api_url_not_allowed' => 'Cette URL n’est pas autorisée (réseau local / hôte interdit).',
    'source_file_format_not_supported' => 'Ce format de fichier n’est pas pris en charge pour la normalisation (utilisez CSV, JSON ou XML).',
    'normalization_mapping_required' => 'Configurez normalizationMapping (keyFields et/ou valueFields) avant d’actualiser.',
    'snapshot_refreshed' => 'Actualisation du snapshot normalisé terminée.',
    'query_sources_required' => 'Au moins une source (alias) est requise.',
    'query_invalid_source_entry' => 'Chaque source doit avoir un alias non vide et un sourceId valide.',
    'query_columns_required' => 'Au moins une colonne est requise.',
    'query_join_on_required' => 'join.on est obligatoire lorsqu’il y a plusieurs sources.',
    'query_join_type_invalid' => 'join.type doit être inner ou left.',
    'query_source_not_in_document' => 'Une ou plusieurs sources n’appartiennent pas à ce document.',
    'query_no_snapshot' => 'Aucun snapshot normalisé réussi pour l’alias :alias. Lancez d’abord un refresh.',
    'query_invalid_formula' => 'Expression de formule invalide.',
    'query_invalid_aggregation' => 'Spécification d’agrégation invalide.',
];
