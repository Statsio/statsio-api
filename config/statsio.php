<?php

return [

    'data_ingestion' => [
        // Nombre maximum de lignes traitées par dataset
        'max_rows' => (int) env('STATS_DATA_MAX_SNAPSHOT_ROWS', 500_000),

        // Taille maximale des fichiers uploadés (en Ko)
        'max_file_size_kb' => (int) env('DATA_INGESTION_MAX_FILE_SIZE_KB', 102_400), // 100 Mo

        // Disque de stockage (local ou s3)
        'storage_disk' => env('FILESYSTEM_DISK', 'local'),

        // Disque pour les fichiers Parquet des datasets (local en dev, r2-datasets en prod)
        'datasets_disk' => env('DATASETS_DISK', 'local'),
    ],

    'media' => [
        // Disque pour les médias uploadés (avatars exclus, cf. Google) (public en dev, r2-media en prod)
        // "public" (et non "local") car les logos de chaîne TV sont servis via une URL directe, pas via le contrôleur media.file
        'disk' => env('MEDIA_DISK', 'public'),
    ],

];
