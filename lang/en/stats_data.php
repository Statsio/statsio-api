<?php

return [
    'created' => 'StatsData document created.',
    'updated' => 'StatsData document updated.',
    'not_found' => 'Document not found.',
    'source_created' => 'Data source created.',
    'source_updated' => 'Data source updated.',
    'source_not_found' => 'Data source not found.',
    'source_invalid_api_url' => 'Invalid API URL.',
    'source_api_url_not_allowed' => 'This URL is not allowed (local / forbidden host).',
    'source_file_format_not_supported' => 'This file format is not supported for normalization (use CSV, JSON, or XML).',
    'normalization_mapping_required' => 'Configure normalizationMapping (keyFields and/or valueFields) before refreshing.',
    'snapshot_refreshed' => 'Normalized snapshot refresh completed.',
    'query_sources_required' => 'At least one source alias is required.',
    'query_invalid_source_entry' => 'Each source must include a non-empty alias and a valid sourceId.',
    'query_columns_required' => 'At least one column is required.',
    'query_join_on_required' => 'join.on is required when using multiple sources.',
    'query_join_type_invalid' => 'join.type must be inner or left.',
    'query_source_not_in_document' => 'One or more sources do not belong to this document.',
    'query_no_snapshot' => 'No successful normalized snapshot for source alias :alias. Run refresh first.',
    'query_invalid_formula' => 'Invalid formula expression.',
    'query_invalid_aggregation' => 'Invalid aggregation spec.',
];
