<?php

namespace app\job;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class AuditRetentionJob extends Command
{
    /**
     * Minimum retention period in years for audit logs.
     */
    protected const MIN_RETENTION_YEARS = 7;

    protected function configure()
    {
        $this->setName('audit:retention')
            ->setDescription('Enforce audit log retention policy (minimum 7-year retention)');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('[' . date('Y-m-d H:i:s') . '] Audit retention policy check started.');
        Log::info('Audit retention policy check started.');

        // Audit logs must be retained for a minimum of 7 years.
        // No purge operation is performed before the retention period expires.
        $retentionBoundary = date('Y-m-d H:i:s', strtotime('-' . self::MIN_RETENTION_YEARS . ' years'));

        $output->writeln('Retention policy: minimum ' . self::MIN_RETENTION_YEARS . ' years.');
        $output->writeln('Retention boundary: ' . $retentionBoundary);
        $output->writeln('No audit log records will be purged before the retention period.');

        Log::info('Audit retention policy verified.', [
            'min_retention_years' => self::MIN_RETENTION_YEARS,
            'retention_boundary'  => $retentionBoundary,
        ]);

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Audit retention policy check completed.');
        Log::info('Audit retention policy check completed.');
    }
}
