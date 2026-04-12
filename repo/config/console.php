<?php

return [
    'commands' => [
        \app\job\NightlyAnalyticsJob::class,
        \app\job\ScheduledReportJob::class,
        \app\job\RetentionCleanupJob::class,
        \app\job\AuditRetentionJob::class,
        \app\job\SeedAdminCommand::class,
    ],
];
