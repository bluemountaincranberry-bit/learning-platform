<?php

return [
    'adaptive' => [
        // Keep enabled after rollout; set ADAPTIVE_FLOW_ENABLED=false for an
        // immediate rollback to the client-side legacy fallback.
        'enabled' => (bool) env('ADAPTIVE_FLOW_ENABLED', true),
    ],
];
