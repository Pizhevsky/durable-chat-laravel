<?php

return [
    'central_node_id' => env('DCR_CENTRAL_NODE_ID', 'laravel-central'),
    'recovery_format' => env('DCR_RECOVERY_FORMAT', 'durable-chat-recovery-v1'),
    'helper_shared_secret' => env('DCR_HELPER_SHARED_SECRET', 'local-dev-helper-secret'),
    'trusted_helper_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('DCR_TRUSTED_HELPER_IDS', 'helper-demo')),
    ))),
    'helper_signature_tolerance_seconds' => (int) env('DCR_HELPER_SIGNATURE_TOLERANCE_SECONDS', 300),
];
