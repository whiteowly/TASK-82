<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\model\FreightRule;
use app\model\SettlementLine;
use app\model\SettlementStatement;
use app\service\audit\AuditService;
use app\service\settlement\FreightCalculatorService;
use app\service\settlement\ReconciliationService;
use app\service\settlement\StatementService;
use app\validate\FreightRuleValidate;
use think\exception\ValidateException;
use think\Response;

class SettlementController extends BaseController
{
    /**
     * POST /api/v1/settlements/freight-rules
     *
     * Create a new freight rule.
     */
    public function createFreightRule(): Response
    {
        $allowed = ['finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance clerks or administrators can perform this action.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        try {
            validate(FreightRuleValidate::class)->check($input);
        } catch (ValidateException $e) {
            return $this->error('VALIDATION_FAILED', $e->getMessage(), [], 422);
        }

        $siteId = (int) $input['site_id'];
        if (!$this->canAccessSite($siteId)) {
            return $this->error('FORBIDDEN_SITE_SCOPE', 'You do not have access to this site.', [], 403);
        }

        $rule = FreightRule::create([
            'site_id'            => $siteId,
            'name'               => $input['name'],
            'distance_band_json' => json_encode($input['distance_bands'] ?? []),
            'weight_tiers_json'  => json_encode($input['weight_tiers'] ?? []),
            'volume_tiers_json'  => json_encode($input['volume_tiers'] ?? []),
            'surcharges_json'    => json_encode($input['surcharges'] ?? []),
            'tax_rate'           => $input['tax_rate'],
            'active'             => 1,
        ]);

        return $this->success([
            'id'      => $rule->id,
            'message' => 'Freight rule created.',
        ], 201);
    }

    /**
     * GET /api/v1/settlements/freight-rules
     *
     * List freight rules.
     */
    private const READ_ROLES = ['finance_clerk', 'administrator', 'auditor'];

    public function listFreightRules(): Response
    {
        if (empty(array_intersect($this->request->roles, self::READ_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance, admin, or auditor roles can access freight rules.', [], 403);
        }
        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = min(100, max(1, (int) $this->request->get('per_page', 20)));

        $query = FreightRule::order('created_at', 'desc');
        $this->applySiteScope($query);

        $total      = $query->count();
        $totalPages = (int) ceil($total / $perPage);
        $items      = $query->page($page, $perPage)
                            ->select()
                            ->toArray();

        return $this->success([
            'items'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * PUT /api/v1/settlements/freight-rules/:id
     *
     * Update a freight rule.
     */
    public function updateFreightRule($id): Response
    {
        $allowed = ['finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance clerks or administrators can perform this action.', [], 403);
        }

        $query = FreightRule::where('id', (int) $id);
        $this->applySiteScope($query);
        $rule = $query->find();

        if (!$rule) {
            return $this->error('NOT_FOUND', 'Freight rule not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        $updateData = [];
        if (isset($input['name'])) {
            $updateData['name'] = $input['name'];
        }
        if (isset($input['distance_bands'])) {
            $updateData['distance_band_json'] = json_encode($input['distance_bands']);
        }
        if (isset($input['weight_tiers'])) {
            $updateData['weight_tiers_json'] = json_encode($input['weight_tiers']);
        }
        if (isset($input['volume_tiers'])) {
            $updateData['volume_tiers_json'] = json_encode($input['volume_tiers']);
        }
        if (isset($input['surcharges'])) {
            $updateData['surcharges_json'] = json_encode($input['surcharges']);
        }
        if (isset($input['tax_rate'])) {
            $updateData['tax_rate'] = $input['tax_rate'];
        }
        if (isset($input['active'])) {
            $updateData['active'] = (int) $input['active'];
        }

        if (!empty($updateData)) {
            $rule->save($updateData);
        }

        return $this->success([
            'id'      => (int) $id,
            'message' => 'Freight rule updated.',
        ]);
    }

    /**
     * POST /api/v1/settlements/generate
     *
     * Generate a new settlement.
     */
    public function generate(StatementService $statementService): Response
    {
        $allowed = ['finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance clerks or administrators can perform this action.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        if (empty($input['site_id']) || empty($input['period'])) {
            return $this->error('VALIDATION_FAILED', 'site_id and period are required.', [], 422);
        }

        $siteId = (int) $input['site_id'];
        if (!$this->canAccessSite($siteId)) {
            return $this->error('FORBIDDEN_SITE_SCOPE', 'You do not have access to this site.', [], 403);
        }

        $statement = $statementService->generate($siteId, $input['period'], $this->request->userId);

        return $this->success([
            'settlement_id' => $statement['id'] ?? null,
            'status'        => $statement['status'] ?? 'generating',
            'message'       => 'Settlement generation started.',
        ], 202);
    }

    /**
     * GET /api/v1/settlements/:id
     *
     * Read a single settlement.
     */
    public function read($id, ReconciliationService $reconciliationService): Response
    {
        if (empty(array_intersect($this->request->roles, self::READ_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance, admin, or auditor roles can view settlements.', [], 403);
        }
        $query = SettlementStatement::where('id', (int) $id);
        $this->applySiteScope($query);
        $statement = $query->find();

        if (!$statement) {
            return $this->error('NOT_FOUND', 'Settlement statement not found.', [], 404);
        }

        $data = $statement->toArray();

        $data['lines'] = SettlementLine::where('statement_id', (int) $id)
            ->select()
            ->toArray();

        $data['variances'] = $reconciliationService->getVariances((int) $id);

        return $this->success($data);
    }

    /**
     * POST /api/v1/settlements/:id/reconcile
     *
     * Reconcile a settlement against source data.
     */
    public function reconcile($id, ReconciliationService $reconciliationService): Response
    {
        $allowed = ['finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance clerks or administrators can perform this action.', [], 403);
        }

        $query = SettlementStatement::where('id', (int) $id);
        $this->applySiteScope($query);
        $statement = $query->find();

        if (!$statement) {
            return $this->error('NOT_FOUND', 'Settlement statement not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        $reconciliationService->recordReconciliation(
            (int) $id,
            is_array($input['notes'] ?? null) ? $input['notes'] : [],
            $this->request->userId
        );

        return $this->success([
            'settlement_id' => (int) $id,
            'status'        => 'reconciled',
            'message'       => 'Reconciliation recorded.',
        ]);
    }

    /**
     * POST /api/v1/settlements/:id/submit
     *
     * Submit a settlement for approval (Finance Clerk).
     */
    public function submit($id, StatementService $statementService, AuditService $auditService): Response
    {
        $allowed = ['finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance clerks or administrators can perform this action.', [], 403);
        }

        $query = SettlementStatement::where('id', (int) $id);
        $this->applySiteScope($query);
        $statement = $query->find();

        if (!$statement) {
            return $this->error('NOT_FOUND', 'Settlement statement not found.', [], 404);
        }

        if ($statement->status === 'approved_locked') {
            return $this->error('RESOURCE_LOCKED', 'Statement is already approved and locked.', [], 423);
        }

        $statementService->submit((int) $id, $this->request->userId);

        $auditService->log(
            'settlement.submit',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            $statement->site_id,
            'settlement_statement',
            (int) $id,
            $this->request->requestId,
            'Settlement submitted for approval'
        );

        return $this->success([
            'settlement_id' => (int) $id,
            'status'        => 'submitted',
            'message'       => 'Settlement submitted for approval.',
        ]);
    }

    /**
     * POST /api/v1/settlements/:id/approve
     *
     * Give final approval to a settlement (Administrator only).
     */
    public function approveFinal($id, StatementService $statementService, AuditService $auditService): Response
    {
        if (!in_array('administrator', $this->request->roles, true)) {
            return $this->error('FORBIDDEN_ROLE', 'Only administrators can approve settlements.', [], 403);
        }

        $query = SettlementStatement::where('id', (int) $id);
        $this->applySiteScope($query);
        $statement = $query->find();

        if (!$statement) {
            return $this->error('NOT_FOUND', 'Settlement statement not found.', [], 404);
        }

        if ($statement->status === 'approved_locked') {
            return $this->error('RESOURCE_LOCKED', 'Statement is already approved and locked.', [], 423);
        }

        $statementService->approveFinal((int) $id, $this->request->userId);

        $auditService->log(
            'settlement.approve',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            $statement->site_id,
            'settlement_statement',
            (int) $id,
            $this->request->requestId,
            'Settlement approved and locked'
        );

        return $this->success([
            'settlement_id' => (int) $id,
            'status'        => 'approved_locked',
            'message'       => 'Settlement approved and locked.',
        ]);
    }

    /**
     * POST /api/v1/settlements/:id/reverse
     *
     * Reverse a settlement.
     */
    public function reverse($id, StatementService $statementService, AuditService $auditService): Response
    {
        $allowed = ['finance_clerk', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance or admin can reverse settlements.', [], 403);
        }

        $query = SettlementStatement::where('id', (int) $id);
        $this->applySiteScope($query);
        $statement = $query->find();

        if (!$statement) {
            return $this->error('NOT_FOUND', 'Settlement statement not found.', [], 404);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];

        if (empty($input['reason'])) {
            return $this->error('VALIDATION_FAILED', 'Reversal reason is required.', [], 422);
        }

        $reversal = $statementService->reverse((int) $id, $this->request->userId, $input['reason']);

        $auditService->log(
            'settlement.reverse',
            $this->request->userId,
            $this->request->roles[0] ?? 'unknown',
            $statement->site_id,
            'settlement_statement',
            (int) $id,
            $this->request->requestId,
            'Settlement reversed: ' . $input['reason']
        );

        return $this->success([
            'settlement_id' => (int) $id,
            'reversal'      => $reversal,
            'status'        => 'reversed',
            'message'       => 'Settlement reversed.',
        ]);
    }

    /**
     * GET /api/v1/settlements/:id/audit-trail
     *
     * Retrieve the audit trail for a settlement.
     */
    /**
     * GET /api/v1/finance/settlements
     */
    public function listStatements(): Response
    {
        if (empty(array_intersect($this->request->roles, self::READ_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance, admin, or auditor roles can list settlements.', [], 403);
        }
        $page = (int)($this->request->get('page', 1));
        $perPage = (int)($this->request->get('per_page', 20));

        $query = SettlementStatement::order('id', 'desc');
        $this->applySiteScope($query);

        $status = $this->request->get('status');
        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $items = $query->page($page, $perPage)->select()->toArray();

        return $this->success([
            'items'      => $items,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    public function auditTrail($id, AuditService $auditService): Response
    {
        if (empty(array_intersect($this->request->roles, self::READ_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Only finance, admin, or auditor roles can view audit trails.', [], 403);
        }
        $query = SettlementStatement::where('id', (int) $id);
        $this->applySiteScope($query);
        $statement = $query->find();

        if (!$statement) {
            return $this->error('NOT_FOUND', 'Settlement statement not found.', [], 404);
        }

        $entries = $auditService->query([
            'target_type' => 'settlement_statement',
            'target_id'   => (int) $id,
        ]);

        return $this->success([
            'settlement_id' => (int) $id,
            'entries'        => $entries,
        ]);
    }
}
