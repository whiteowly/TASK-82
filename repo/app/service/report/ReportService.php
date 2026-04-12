<?php
declare(strict_types=1);

namespace app\service\report;

use think\facade\Db;
use think\exception\ValidateException;

class ReportService
{
    private CsvExportService $csvExportService;

    public function __construct(CsvExportService $csvExportService)
    {
        $this->csvExportService = $csvExportService;
    }
    /**
     * Create a new report definition.
     *
     * @param array $data   Report definition attributes (name, description, dimensions_json, filters_json, columns_json).
     * @param int   $userId Creating user ID.
     * @return array The created definition record.
     */
    public function createDefinition(array $data, int $userId): array
    {
        if (empty($data['name'])) {
            throw new ValidateException('Report name is required.');
        }

        $now = date('Y-m-d H:i:s');
        $id = Db::table('report_definitions')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'dimensions_json' => isset($data['dimensions_json']) ? (is_array($data['dimensions_json']) ? json_encode($data['dimensions_json']) : $data['dimensions_json']) : null,
            'filters_json' => isset($data['filters_json']) ? (is_array($data['filters_json']) ? json_encode($data['filters_json']) : $data['filters_json']) : null,
            'columns_json' => isset($data['columns_json']) ? (is_array($data['columns_json']) ? json_encode($data['columns_json']) : $data['columns_json']) : null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Db::table('report_definitions')->where('id', $id)->find();
    }

    /**
     * Find a report definition by ID.
     *
     * @param int $id
     * @return array|null Definition data or null if not found.
     */
    public function findDefinition(int $id): ?array
    {
        $record = Db::table('report_definitions')->where('id', $id)->find();
        return $record ?: null;
    }

    /**
     * Update an existing report definition.
     *
     * @param int   $id
     * @param array $data Updated attributes.
     * @return array The updated definition record.
     */
    public function updateDefinition(int $id, array $data): array
    {
        $existing = Db::table('report_definitions')->where('id', $id)->find();
        if (!$existing) {
            throw new ValidateException('Report definition not found.');
        }

        $update = ['updated_at' => date('Y-m-d H:i:s')];

        if (array_key_exists('name', $data)) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $update['description'] = $data['description'];
        }
        if (array_key_exists('dimensions_json', $data)) {
            $update['dimensions_json'] = is_array($data['dimensions_json']) ? json_encode($data['dimensions_json']) : $data['dimensions_json'];
        }
        if (array_key_exists('filters_json', $data)) {
            $update['filters_json'] = is_array($data['filters_json']) ? json_encode($data['filters_json']) : $data['filters_json'];
        }
        if (array_key_exists('columns_json', $data)) {
            $update['columns_json'] = is_array($data['columns_json']) ? json_encode($data['columns_json']) : $data['columns_json'];
        }

        Db::table('report_definitions')->where('id', $id)->update($update);

        return Db::table('report_definitions')->where('id', $id)->find();
    }

    /**
     * Queue a report run for the given definition.
     *
     * @param int   $definitionId
     * @param int   $userId
     * @param array $siteScopes  Empty array means cross-site (privileged). Non-empty restricts to those site IDs.
     * @param bool  $privileged  True if the caller is a privileged role (admin/auditor). When false, empty siteScopes will fail the run.
     * @return array The created run record with status 'queued'.
     */
    public function queueRun(int $definitionId, int $userId, array $siteScopes = [], bool $privileged = true): array
    {
        $definition = Db::table('report_definitions')->where('id', $definitionId)->find();
        if (!$definition) {
            throw new ValidateException('Report definition not found.');
        }

        $now = date('Y-m-d H:i:s');
        $id = Db::table('report_runs')->insertGetId([
            'definition_id' => $definitionId,
            'status' => 'queued',
            'created_at' => $now,
        ]);

        // Execute synchronously for the scaffold phase
        $this->executeRun($id, $siteScopes, $privileged);

        return Db::table('report_runs')->where('id', $id)->find();
    }

    /**
     * Execute a report run: query data, generate CSV, create artifact record.
     *
     * @param int   $runId
     * @param array $siteScopes  Empty means cross-site (no restriction) — only valid when $privileged is true.
     * @param bool  $privileged  When false, empty $siteScopes is treated as a scope-resolution failure and the run is failed.
     * @return void
     */
    public function executeRun(int $runId, array $siteScopes = [], bool $privileged = true): void
    {
        $run = Db::table('report_runs')->where('id', $runId)->find();
        if (!$run) {
            throw new ValidateException('Report run not found.');
        }

        // Defense-in-depth: non-privileged callers with empty scopes must not run cross-site
        if (!$privileged && empty($siteScopes)) {
            Db::table('report_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $definition = Db::table('report_definitions')->where('id', $run['definition_id'])->find();
        if (!$definition) {
            Db::table('report_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        Db::table('report_runs')->where('id', $runId)->update([
            'status' => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $dimensions = !empty($definition['dimensions_json'])
                ? json_decode($definition['dimensions_json'], true) : [];
            $filters = !empty($definition['filters_json'])
                ? json_decode($definition['filters_json'], true) : [];
            $columns = !empty($definition['columns_json'])
                ? json_decode($definition['columns_json'], true) : [];

            $reportType = $dimensions['type'] ?? ($filters['type'] ?? 'general');

            $data = $this->queryReportData($reportType, $dimensions, $filters, $siteScopes);

            $csvColumns = !empty($columns) ? $columns : (!empty($data) ? array_keys($data[0]) : []);

            $filePath = $this->csvExportService->generateCsv($data, $csvColumns, []);

            $now = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', strtotime('+180 days'));

            // report_artifacts schema: run_id, file_path, file_size, sha256_hash, created_at
            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
            $sha256 = file_exists($filePath) ? hash_file('sha256', $filePath) : '';

            Db::table('report_artifacts')->insert([
                'run_id'      => $runId,
                'file_path'   => $filePath,
                'file_size'   => $fileSize,
                'sha256_hash' => $sha256,
                'created_at'  => $now,
            ]);

            // report_runs schema: artifact_path, expires_at, started_at, completed_at
            Db::table('report_runs')->where('id', $runId)->update([
                'status'        => 'succeeded',
                'artifact_path' => $filePath,
                'expires_at'    => $expiresAt,
                'completed_at'  => $now,
            ]);
        } catch (\Throwable $e) {
            Db::table('report_runs')->where('id', $runId)->update([
                'status'       => 'failed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            throw $e;
        }
    }

    /**
     * Query report data based on the report type and definition parameters.
     *
     * @param string $reportType
     * @param array  $dimensions
     * @param array  $filters
     * @param array  $siteScopes  Non-empty enforces site_id restriction for non-privileged users.
     * @return array
     */
    private function queryReportData(string $reportType, array $dimensions, array $filters, array $siteScopes = []): array
    {
        $query = null;

        switch ($reportType) {
            case 'participation':
                $query = Db::table('participants')
                    ->field('site_id, COUNT(*) as participant_count')
                    ->group('site_id');
                break;

            case 'retention':
                $query = Db::table('participants')
                    ->alias('p')
                    ->leftJoin('orders o', 'o.participant_id = p.id')
                    ->field('p.id, p.name, COUNT(o.id) as order_count, IF(COUNT(o.id) > 1, "repeat", "one-time") as participant_type')
                    ->group('p.id, p.name');
                break;

            case 'pass_rate':
                $query = Db::table('recipes')
                    ->field('status, COUNT(*) as recipe_count, ROUND(COUNT(CASE WHEN status = "approved" THEN 1 END) * 100.0 / COUNT(*), 2) as approval_rate')
                    ->group('status');
                break;

            case 'regional_distribution':
                $query = Db::table('orders')
                    ->alias('o')
                    ->leftJoin('participants p', 'p.id = o.participant_id')
                    ->field('o.site_id, COUNT(DISTINCT o.id) as order_count, COUNT(DISTINCT o.participant_id) as participant_count')
                    ->group('o.site_id');
                break;

            default:
                // General report: determine table from dimensions or default to orders
                $table = $dimensions['table'] ?? 'orders';
                $allowedTables = ['orders', 'participants', 'recipes', 'settlement_statements'];
                if (!in_array($table, $allowedTables, true)) {
                    $table = 'orders';
                }
                $query = Db::table($table);
                break;
        }

        // Apply common filters
        if (!empty($filters['site_id'])) {
            $siteCol = in_array($reportType, ['retention']) ? 'p.site_id' : 'site_id';
            $query->where($siteCol, $filters['site_id']);
        }
        if (!empty($filters['site_ids']) && is_array($filters['site_ids'])) {
            $siteCol = in_array($reportType, ['retention']) ? 'p.site_id' : 'site_id';
            $query->whereIn($siteCol, $filters['site_ids']);
        }
        if (!empty($filters['date_from'])) {
            $dateCol = in_array($reportType, ['retention']) ? 'p.created_at' : 'created_at';
            $query->where($dateCol, '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $dateCol = in_array($reportType, ['retention']) ? 'p.created_at' : 'created_at';
            $query->where($dateCol, '<=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Enforce site-scope restriction for non-privileged users
        if (!empty($siteScopes)) {
            $siteCol = in_array($reportType, ['retention']) ? 'p.site_id'
                : (in_array($reportType, ['regional_distribution']) ? 'o.site_id' : 'site_id');
            $query->whereIn($siteCol, $siteScopes);
        }

        return $query->select()->toArray();
    }

    /**
     * Get a report run by ID, including artifact info if available.
     *
     * @param int $id
     * @return array|null Run data or null if not found.
     */
    public function getRun(int $id): ?array
    {
        $run = Db::table('report_runs')->where('id', $id)->find();
        if (!$run) {
            return null;
        }

        $artifact = Db::table('report_artifacts')->where('run_id', $id)->find();
        if ($artifact) {
            $run['artifact'] = $artifact;
        }

        return $run;
    }

    /**
     * List report runs, optionally filtered.
     *
     * @param array $filters Query filters (definition_id, status, page, per_page).
     * @return array Paginated list of run records.
     */
    public function listRuns(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));

        $query = Db::table('report_runs')->alias('r');

        // Ownership scoping: non-privileged users only see runs for their own definitions
        $userRoles = $filters['user_roles'] ?? [];
        $privilegedRoles = ['administrator', 'auditor'];
        if (!empty($filters['user_id']) && empty(array_intersect($userRoles, $privilegedRoles))) {
            $query->join('report_definitions d', 'r.definition_id = d.id')
                  ->where('d.created_by', $filters['user_id']);
        }

        if (!empty($filters['definition_id'])) {
            $query->where('r.definition_id', $filters['definition_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        $total = (clone $query)->count();

        $rows = $query->field('r.*')
            ->order('r.created_at', 'desc')
            ->limit(($page - 1) * $perPage, $perPage)
            ->select()
            ->toArray();

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Schedule recurring execution of a report definition.
     *
     * @param int   $definitionId
     * @param array $schedule Schedule configuration (cadence, next_run_at, active).
     * @return void
     */
    public function scheduleReport(int $definitionId, array $schedule): void
    {
        $definition = Db::table('report_definitions')->where('id', $definitionId)->find();
        if (!$definition) {
            throw new ValidateException('Report definition not found.');
        }

        $existing = Db::table('report_schedules')
            ->where('definition_id', $definitionId)
            ->find();

        $now = date('Y-m-d H:i:s');

        // Ensure next_run_at is set so the schedule is immediately runnable
        if (empty($schedule['next_run_at'])) {
            $schedule['next_run_at'] = $now;
        }

        if ($existing) {
            $update = ['updated_at' => $now];
            if (isset($schedule['cadence'])) {
                $update['cadence'] = $schedule['cadence'];
            }
            if (isset($schedule['next_run_at'])) {
                $update['next_run_at'] = $schedule['next_run_at'];
            }
            if (isset($schedule['active'])) {
                $update['active'] = $schedule['active'] ? 1 : 0;
            }
            Db::table('report_schedules')->where('id', $existing['id'])->update($update);
        } else {
            Db::table('report_schedules')->insert([
                'definition_id' => $definitionId,
                'cadence' => $schedule['cadence'] ?? 'daily',
                'next_run_at' => $schedule['next_run_at'] ?? null,
                'active' => isset($schedule['active']) ? ($schedule['active'] ? 1 : 0) : 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
