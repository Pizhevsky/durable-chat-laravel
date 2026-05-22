<?php

return [
    'central_node_id' => env('DCR_CENTRAL_NODE_ID', 'laravel-central'),
    'recovery_format' => env('DCR_RECOVERY_FORMAT', 'durable-chat-recovery-v1'),
    'sync_pull_limit' => (int) env('DCR_SYNC_PULL_LIMIT', 500),
    'max_sync_pull_limit' => (int) env('DCR_MAX_SYNC_PULL_LIMIT', 1000),
    'recovery_export_limit' => (int) env('DCR_RECOVERY_EXPORT_LIMIT', 10000),
];
