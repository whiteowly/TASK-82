<?php

namespace app\job;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;
use app\service\analytics\MetricService;

class NightlyAnalyticsJob extends Command
{
    protected function configure()
    {
        $this->setName('analytics:nightly')
            ->setDescription('Run nightly analytics snapshot generation');
    }

    /**
     * Default schedule: 2:00 AM daily
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('[' . date('Y-m-d H:i:s') . '] Nightly analytics snapshot generation started.');
        Log::info('Nightly analytics snapshot generation started.');

        try {
            $siteIds = Db::table('sites')
                ->column('id');

            $metricService = new MetricService();
            $metricService->generateSnapshots($siteIds);

            $output->writeln('[' . date('Y-m-d H:i:s') . '] Analytics snapshots generated successfully for ' . count($siteIds) . ' site(s).');
            Log::info('Analytics snapshots generated successfully for ' . count($siteIds) . ' site(s).');
        } catch (\Exception $e) {
            $output->writeln('<error>Error generating analytics snapshots: ' . $e->getMessage() . '</error>');
            Log::error('Error generating analytics snapshots: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Nightly analytics snapshot generation completed.');
        Log::info('Nightly analytics snapshot generation completed.');
    }
}
