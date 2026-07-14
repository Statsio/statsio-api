<?php

namespace App\Domain\DataIngestion\Exceptions;

/**
 * Levée quand une requête Studio sur un dataset "live" demande une opération
 * que le mapping de la source (query_mapping) ne sait pas traduire côté API
 * externe : colonne/opérateur de filtre non mappé, tri hors sortable_columns,
 * jointure, agrégation, ou distinct sur une colonne non couverte.
 */
class UnsupportedLiveQueryOperationException extends \RuntimeException {}
