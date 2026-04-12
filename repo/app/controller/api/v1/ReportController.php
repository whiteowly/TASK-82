<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\model\ExportLog;
use app\service\audit\AuditService;
use app\service\report\CsvExportService;
use app\service\report\ReportService;
use app\service\security\FieldMaskingService;
use app\validate\ReportDefinitionValidate;
use think\exception\ValidateException;
use think\facade\Db;
use think\Response;

class ReportController extends BaseController
{
    /**
     * GET /api/v1/reports/definitions
     */
    private const REPORT_ROLES = ['operations_analyst', 'finance_clerk', 'administrator', 'auditor'];

    public function listDefinitions(ReportService $reportService): Response
    {
        $userRoles = $this->request->roles ?? [];
        if (empty(array_intersect($userRoles, self::REPORT_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Report access requires analyst, finance, admin, or auditor role.', [], 403);
        }
        $privilegedRoles = ['administrator', 'auditor'];

        $query = \think\facade\Db::name('report_definitions')
            ->order('id', 'desc');

        // Non-privileged users can only see their own definitions
        if (empty(array_intersect($userRoles, $privilegedRoles))) {
            $query->where('created_by', $this->request->userId);
        }

        $items = $query->select()->toArray();

        // Decode JSON fields for frontend consistency
        foreach ($items as &$item) {
            $item['dimensions'] = !empty($item['dimensions_json']) ? json_decode($item['dimensions_json'], true) : [];
            $item['filters'] = !empty($item['filters_json']) ? json_decode($item['filters_json'], true) : [];
            $item['columns'] = !empty($item['columns_json']) ? json_decode($item['columns_json'], true) : [];
        }
        unset($item);

        return $this->success(['items' => $items]);
    }

    /**
     * POST /api/v1/reports/definitions
     *
     * Create a new report definition.
     */
    public function createDefinition(ReportService $reportService): Response
    {
        $allowed = ['operations_analyst', 'finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to create report definitions.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        try {
            validate(ReportDefinitionValidate::class)->check($input);
        } catch (ValidateException $e) {
            return $this->error('VALIDATION_FAILED', $e->getMessage(), [], 422);
        }

        unset($input['created_by']);

        // Map frontend keys to service keys
        $input['dimensions_json'] = $input['dimensions_json'] ?? $input['dimensions'] ?? null;
        $input['filters_json'] = $input['filters_json'] ?? $input['filters'] ?? null;
        $input['columns_json'] = $input['columns_json'] ?? $input['columns'] ?? null;

        $definition = $reportService->createDefinition($input, $this->request->userId);

        return $this->success([
            'id'      => $definition['id'] ?? null,
            'message' => 'Report definition created.',
        ], 201);
    }

    /**
     * GET /api/v1/reports/definitions/:id
     *
     * Read a report definition.
     */
    public function readDefinition($id, ReportService $reportService): Response
    {
        $allowed = ['operations_analyst', 'finance_clerk', 'administrator', 'auditor'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to read report definitions.', [], 403);
        }

        $definition = $reportService->findDefinition((int) $id);

        if (!$definition) {
            return $this->error('NOT_FOUND', 'Report definition not found.', [], 404);
        }

        // Ownership check: non-privileged users can only see their own definitions
        if (!$this->isPrivilegedOrOwner($definition)) {
            return $this->error('NOT_FOUND', 'Report definition not found.', [], 404);
        }

        // Add decoded frontend-friendly keys
        $definition['dimensions'] = !empty($definition['dimensions_json']) ? json_decode($definition['dimensions_json'], true) : [];
        $definition['filters'] = !empty($definition['filters_json']) ? json_decode($definition['filters_json'], true) : [];
        $definition['columns'] = !empty($definition['columns_json']) ? json_decode($definition['columns_json'], true) : [];

        return $this->success($definition);
    }

    /**
     * PUT /api/v1/reports/definitions/:id
     *
     * Update a report definition.
     */
    public function updateDefinition($id, ReportService $reportService): Response
    {
        $allowed = ['operations_analyst', 'finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to update report definitions.', [], 403);
        }

        $existing = $reportService->findDefinition((int) $id);

        if (!$existing) {
            return $this->error('NOT_FOUND', 'Report definition not found.', [], 404);
        }

        if (!$this->isPrivilegedOrOwner($existing)) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to update this definition.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        // Map frontend keys to service keys
        $input['dimensions_json'] = $input['dimensions_json'] ?? $input['dimensions'] ?? null;
        $input['filters_json'] = $input['filters_json'] ?? $input['filters'] ?? null;
        $input['columns_json'] = $input['columns_json'] ?? $input['columns'] ?? null;

        $definition = $reportService->updateDefinition((int) $id, $input);

        return $this->success([
            'id'      => (int) $id,
            'message' => 'Report definition updated.',
        ]);
    }

    /**
     * POST /api/v1/reports/definitions/:id/run
     *
     * Execute a report definition immediately.
     */
    public function runReport($id, ReportService $reportService): Response
    {
        $allowed = ['operations_analyst', 'finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to run reports.', [], 403);
        }

        $existing = $reportService->findDefinition((int) $id);

        if (!$existing) {
            return $this->error('NOT_FOUND', 'Report definition not found.', [], 404);
        }

        // Ownership check: non-privileged users can only run their own definitions
        $userRoles = $this->request->roles ?? [];
        $privilegedRoles = ['administrator', 'auditor'];
        if (empty(array_intersect($userRoles, $privilegedRoles))) {
            if (($existing['created_by'] ?? null) != $this->request->userId) {
                return $this->error('NOT_FOUND', 'Report definition not found.', [], 404);
            }
        }

        $siteScopes = $this->request->siteScopes ?? [];
        $isPrivileged = !empty(array_intersect($userRoles, $privilegedRoles));
        $run = $reportService->queueRun((int) $id, $this->request->userId, $siteScopes, $isPrivileged);

        return $this->success([
            'definition_id' => (int) $id,
            'run_id'        => $run['id'] ?? null,
            'status'        => $run['status'] ?? 'queued',
            'message'       => 'Report execution queued.',
        ], 202);
    }

    /**
     * POST /api/v1/reports/definitions/:id/schedule
     *
     * Schedule a report definition for recurring execution.
     */
    public function scheduleReport($id, ReportService $reportService): Response
    {
        $allowed = ['operations_analyst', 'finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to schedule reports.', [], 403);
        }

        $existing = $reportService->findDefinition((int) $id);

        if (!$existing) {
            return $this->error('NOT_FOUND', 'Report definition not found.', [], 404);
        }

        if (!$this->isPrivilegedOrOwner($existing)) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to schedule this definition.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        if (empty($input['cadence'])) {
            return $this->error('VALIDATION_FAILED', 'Schedule cadence is required.', [], 422);
        }

        $reportService->scheduleReport((int) $id, $input);

        return $this->success([
            'definition_id' => (int) $id,
            'message'       => 'Report schedule created.',
        ], 201);
    }

    /**
     * GET /api/v1/reports/runs
     *
     * List report runs with pagination.
     */
    public function listRuns(ReportService $reportService): Response
    {
        $userRoles = $this->request->roles ?? [];
        if (empty(array_intersect($userRoles, self::REPORT_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Report access requires analyst, finance, admin, or auditor role.', [], 403);
        }

        $filters = [
            'definition_id' => $this->request->get('definition_id'),
            'status'        => $this->request->get('status'),
            'page'          => (int) $this->request->get('page', 1),
            'per_page'      => (int) $this->request->get('per_page', 20),
        ];

        // Pass user context for ownership scoping
        $filters['user_id'] = $this->request->userId;
        $filters['user_roles'] = $this->request->roles ?? [];

        $runsResult = $reportService->listRuns($filters);

        $page = max(1, $filters['page']);
        $perPage = min(100, max(1, $filters['per_page']));
        $total = (int) ($runsResult['total'] ?? 0);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        return $this->success([
            'items'      => $runsResult['data'] ?? [],
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * GET /api/v1/reports/runs/:id
     *
     * Read a single report run.
     */
    public function readRun($id, ReportService $reportService): Response
    {
        $run = $reportService->getRun((int) $id);

        if (!$run) {
            return $this->error('NOT_FOUND', 'Report run not found.', [], 404);
        }

        // Ownership check: verify the run's definition belongs to the user or user is privileged
        $def = $reportService->findDefinition((int) $run['definition_id']);
        if (!$this->isPrivilegedOrOwner($def)) {
            return $this->error('NOT_FOUND', 'Report run not found.', [], 404);
        }

        return $this->success($run);
    }

    /**
     * Check if the current user is privileged (admin/auditor) or owns the definition.
     */
    private function isPrivilegedOrOwner(?array $definition): bool
    {
        if (!$definition) {
            return false;
        }
        $privileged = ['administrator', 'auditor'];
        if (!empty(array_intersect($this->request->roles, $privileged))) {
            return true;
        }
        return ((int) ($definition['created_by'] ?? 0)) === $this->request->userId;
    }

    /**
     * GET /api/v1/reports/runs/:id/download
     *
     * Download the output of a completed report run.
     */
    public function download($id, CsvExportService $csvExportService, AuditService $auditService): Response
    {
        // Ownership check: verify the run's definition belongs to the user or user is privileged
        $run = \think\facade\Db::name('report_runs')->where('id', (int) $id)->find();
        if ($run) {
            $definition = \think\facade\Db::name('report_definitions')
                ->where('id', $run['definition_id'])
                ->find();
            $userRoles = $this->request->roles ?? [];
            $privilegedRoles = ['administrator', 'auditor'];
            if ($definition && empty(array_intersect($userRoles, $privilegedRoles))) {
                if (($definition['created_by'] ?? null) != $this->request->userId) {
                    return $this->error('NOT_FOUND', 'Report artifact not found.', [], 404);
                }
            }
        }

        $artifactPath = $csvExportService->getArtifactPath((int) $id);

        if (!$artifactPath || !file_exists($artifactPath)) {
            return $this->error('NOT_FOUND', 'Report artifact not found.', [], 404);
        }

        $auditService->log(
            'report.download',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            !empty($this->request->siteScopes) ? $this->request->siteScopes[0] : null,
            'report_run',
            (int) $id,
            $this->request->requestId,
            'Downloaded report run artifact'
        );

        ExportLog::create([
            'actor_id'     => $this->request->userId,
            'site_id'      => !empty($this->request->siteScopes) ? $this->request->siteScopes[0] : null,
            'export_type'  => 'report_download',
            'record_count' => 0,
            'reason'       => 'Report run download',
            'request_id'   => $this->request->requestId,
        ]);

        return download($artifactPath);
    }

    /**
     * GET /api/v1/reports/export-csv
     *
     * Export report data as CSV.
     */
    public function exportCsv(
        CsvExportService $csvExportService,
        AuditService $auditService,
        FieldMaskingService $fieldMaskingService
    ): Response {
        $allowed = ['operations_analyst', 'finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to export reports.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        $type    = $input['type'] ?? '';
        $filters = $input['filters'] ?? [];

        $allowedTypes = ['orders', 'participants', 'recipes', 'settlement_statements'];
        if (!in_array($type, $allowedTypes, true)) {
            return $this->error('VALIDATION_FAILED', 'Export type must be one of: ' . implode(', ', $allowedTypes), [], 422);
        }

        $siteScopes = $this->request->siteScopes ?? [];
        $userRoles  = $this->request->roles ?? [];

        // Query data server-side based on type and filters
        $query = Db::name($type);

        // Apply site scope filtering
        if (!empty($siteScopes)) {
            $query->whereIn('site_id', $siteScopes);
        }

        // Apply user-provided filters
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['site_id']) && in_array($filters['site_id'], $siteScopes, true)) {
            $query->where('site_id', $filters['site_id']);
        }

        $data = $query->select()->toArray();

        if (empty($data)) {
            return $this->error('VALIDATION_FAILED', 'No data found for the given type and filters.', [], 422);
        }

        // Determine columns from data keys
        $columns = array_keys($data[0]);

        // Apply field masking via FieldMaskingService
        $sensitiveFields = ['phone', 'tax_id', 'tax_id_encrypted', 'password_hash', 'email', 'national_id', 'bank_account'];
        $maskableFields  = array_intersect($sensitiveFields, $columns);

        $maskedData = array_map(function (array $row) use ($fieldMaskingService, $maskableFields, $userRoles) {
            return $fieldMaskingService->applyMaskingToRecord($row, $maskableFields, $userRoles);
        }, $data);

        $filePath = $csvExportService->generateCsv($maskedData, $columns, []);

        if (empty($filePath) || !file_exists($filePath)) {
            return $this->error('NOT_FOUND', 'Failed to generate CSV export.', [], 500);
        }

        $recordCount = count($maskedData);

        $auditService->log(
            'report.export_csv',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            !empty($siteScopes) ? $siteScopes[0] : null,
            'csv_export',
            null,
            $this->request->requestId,
            "Server-side {$type} CSV export with {$recordCount} rows"
        );

        ExportLog::create([
            'actor_id'     => $this->request->userId,
            'site_id'      => !empty($siteScopes) ? $siteScopes[0] : null,
            'export_type'  => $type . '_csv',
            'record_count' => $recordCount,
            'reason'       => "Server-side {$type} CSV export",
            'request_id'   => $this->request->requestId,
        ]);

        return download($filePath);
    }
}
