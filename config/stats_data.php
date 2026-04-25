<?php

return [
    'max_snapshot_rows' => (int) env('STATS_DATA_MAX_SNAPSHOT_ROWS', 50_000),
    'max_query_rows' => (int) env('STATS_DATA_MAX_QUERY_ROWS', 10_000),
];
