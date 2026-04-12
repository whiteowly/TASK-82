<?php

namespace app\job;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;

class RetentionCleanupJob extends Command
{
    /**
     * Retention period in days for report artifacts.
     */
    protected const RETENTION_DAYS = 180;

    protected function configure()
    {
        $this->setName('retention:cleanup')
            ->setDescription('Clean up expired report artifacts (180-day retention)');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('[' . date('Y-m-d H:i:s') . '] Retention cleanup started.');
        Log::info('Retention cleanup started.');

        try {
            $now = date('Y-m-d H:i:s');

            // Find expired report runs (expires_at has passed)
            $expiredRuns = Db::name('report_runs')
                ->where('expires_at', '<=', $now)
                ->where('status', '<>', 'expired')
                ->whereNotNull('expires_at')
                ->select()
                ->toArray();

            $count = count($expiredRuns);
            $output->writeln("Found {$count} expired report run(s) to clean up.");
            Log::info("Found {$count} expired report run(s) to clean up.");

            foreach ($expiredRuns as $run) {
                try {
                    // Find and remove associated artifact files
                    $artifacts = Db::name('report_artifacts')
                        ->where('run_id', $run['id'])
                        ->select();

                    foreach ($artifacts as $artifact) {
                        if (!empty($artifact['file_path']) && file_exists($artifact['file_path'])) {
                            unlink($artifact['file_path']);
                            $output->writeln("Removed artifact file: {$artifact['file_path']}");
                            Log::info("Removed artifact file: {$artifact['file_path']}");
                        }
                    }

                    // Update report run status to expired
                    Db::name('report_runs')
                        ->where('id', $run['id'])
                        ->update(['status' => 'expired']);

                    $output->writeln("Marked report run #{$run['id']} as expired.");
                    Log::info("Marked report run #{$run['id']} as expired.");
                } catch (\Exception $e) {
                    $output->writeln("<error>Failed to clean up run #{$run['id']}: {$e->getMessage()}</error>");
                    Log::error("Failed to clean up run #{$run['id']}: {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            $output->writeln('<error>Error during retention cleanup: ' . $e->getMessage() . '</error>');
            Log::error('Error during retention cleanup: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Retention cleanup completed.');
        Log::info('Retention cleanup completed.');
    }
}
