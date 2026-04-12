<?php

namespace app\job;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Log;
use app\service\report\ReportService;
use app\service\auth\AuthService;

class ScheduledReportJob extends Command
{
    protected function configure()
    {
        $this->setName('reports:scheduled')
            ->setDescription('Process scheduled report generation');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('[' . date('Y-m-d H:i:s') . '] Scheduled report processing started.');
        Log::info('Scheduled report processing started.');

        try {
            // Query report schedules that are due for execution
            $dueSchedules = Db::name('report_schedules')
                ->where('active', true)
                ->where('next_run_at', '<=', date('Y-m-d H:i:s'))
                ->select();

            $count = count($dueSchedules);
            $output->writeln("Found {$count} scheduled report(s) due for processing.");
            Log::info("Found {$count} scheduled report(s) due for processing.");

            $reportService = app(ReportService::class);
            $authService = app(AuthService::class);

            foreach ($dueSchedules as $schedule) {
                try {
                    // Resolve the definition owner's roles and site scopes
                    $definition = Db::name('report_definitions')
                        ->where('id', $schedule['definition_id'])
                        ->find();

                    $siteScopes = [];
                    $isPrivileged = false;

                    if ($definition) {
                        $ownerContext = $authService->resolveUserRolesAndScopes((int) $definition['created_by']);
                        $ownerRoles = $ownerContext['roles'] ?? [];
                        $crossSiteRoles = ['administrator', 'auditor'];
                        $isPrivileged = !empty(array_intersect($ownerRoles, $crossSiteRoles));
                        if (!$isPrivileged) {
                            $siteScopes = $ownerContext['site_scopes'] ?? [];
                        }
                    }

                    // Queue a report run for each due schedule
                    $runId = Db::name('report_runs')->insertGetId([
                        'definition_id' => $schedule['definition_id'],
                        'status'        => 'queued',
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);

                    // Non-privileged owners with empty scopes must not run cross-site
                    if (!$isPrivileged && empty($siteScopes)) {
                        $reason = 'Non-privileged definition owner has no site scopes assigned; cannot execute report.';
                        Db::name('report_runs')->where('id', $runId)->update([
                            'status'       => 'failed',
                            'completed_at' => date('Y-m-d H:i:s'),
                        ]);
                        $output->writeln("<error>Skipped report run #{$runId} for schedule #{$schedule['id']}: {$reason}</error>");
                        Log::warning("Skipped report run #{$runId} for schedule #{$schedule['id']}: {$reason}");

                        // Still advance the schedule so it does not retry immediately
                    } else {
                        $output->writeln("Queued report run #{$runId} for schedule #{$schedule['id']} (definition #{$schedule['definition_id']}).");
                        Log::info("Queued report run #{$runId} for schedule #{$schedule['id']}.");

                        // Execute the report run with owner's site scope context
                        $reportService->executeRun($runId, $siteScopes, $isPrivileged);

                        $output->writeln("Executed report run #{$runId} for schedule #{$schedule['id']}.");
                        Log::info("Executed report run #{$runId} for schedule #{$schedule['id']}.");
                    }

                    // Advance next_run_at based on cadence
                    $cadence = $schedule['cadence'] ?? 'daily';
                    $currentNextRun = $schedule['next_run_at'] ?? date('Y-m-d H:i:s');
                    switch ($cadence) {
                        case 'weekly':
                            $newNextRun = date('Y-m-d H:i:s', strtotime($currentNextRun . ' +7 days'));
                            break;
                        case 'monthly':
                            $newNextRun = date('Y-m-d H:i:s', strtotime($currentNextRun . ' +1 month'));
                            break;
                        case 'daily':
                        default:
                            $newNextRun = date('Y-m-d H:i:s', strtotime($currentNextRun . ' +1 day'));
                            break;
                    }

                    Db::name('report_schedules')
                        ->where('id', $schedule['id'])
                        ->update([
                            'next_run_at' => $newNextRun,
                            'updated_at'  => date('Y-m-d H:i:s'),
                        ]);

                    $output->writeln("Advanced schedule #{$schedule['id']} next_run_at to {$newNextRun}.");
                    Log::info("Advanced schedule #{$schedule['id']} next_run_at to {$newNextRun}.");
                } catch (\Exception $e) {
                    $output->writeln("<error>Failed to process report for schedule #{$schedule['id']}: {$e->getMessage()}</error>");
                    Log::error("Failed to process report for schedule #{$schedule['id']}: {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            $output->writeln('<error>Error processing scheduled reports: ' . $e->getMessage() . '</error>');
            Log::error('Error processing scheduled reports: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $output->writeln('[' . date('Y-m-d H:i:s') . '] Scheduled report processing completed.');
        Log::info('Scheduled report processing completed.');
    }
}
