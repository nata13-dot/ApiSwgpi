<?php

return [
    'target_readiness' => (int) env('CONTINUITY_TARGET_READINESS', 100),
    'critical_readiness' => (int) env('CONTINUITY_CRITICAL_READINESS', 75),
    'max_backup_age_hours' => (int) env('CONTINUITY_MAX_BACKUP_AGE_HOURS', 26),
];
