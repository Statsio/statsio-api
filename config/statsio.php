<?php

return [

    'data_ingestion' => [
        // Nombre maximum de lignes traitées par dataset
        'max_rows' => (int) env('STATS_DATA_MAX_SNAPSHOT_ROWS', 500_000),

        // Taille maximale des fichiers uploadés (en Ko)
        'max_file_size_kb' => (int) env('DATA_INGESTION_MAX_FILE_SIZE_KB', 102_400), // 100 Mo

        // Disque de stockage (local ou s3)
        'storage_disk' => env('FILESYSTEM_DISK', 'local'),
    ],

];
