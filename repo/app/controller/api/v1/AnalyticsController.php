<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\service\analytics\AnalyticsService;
use think\exception\ValidateException;
use think\Response;

class AnalyticsController extends BaseController
{
    public function dashboard(AnalyticsService $analyticsService): Response
    {
        $allowed = ['operations_analyst', 'administrator', 'auditor'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to access the analytics dashboard.', [], 403);
        }

        $filters = [
            'date_start'      => $this->request->get('date_start') ?: $this->request->get('date_from'),
            'date_end'        => $this->request->get('date_end') ?: $this->request->get('date_to'),
            'site_id'         => $this->request->get('site_id'),
            'community_id'    => $this->request->get('community_id'),
            'group_leader_id' => $this->request->get('group_leader_id'),
            'product_id'      => $this->request->get('product_id'),
        ];

        $data = $analyticsService->getDashboardData($filters, $this->request->siteScopes);

        return $this->success([
            'widgets'      => $data,
            'generated_at' => date('c'),
        ]);
    }

    public function refresh(AnalyticsService $analyticsService): Response
    {
        $allowed = ['operations_analyst', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to refresh analytics.', [], 403);
        }

        $input = json_decode($this->request->getInput(), true) ?: [];
        $scope = $input['scope'] ?? $this->request->siteScopes;

        try {
            $result = $analyticsService->requestRefresh($this->request->userId, $scope);
        } catch (ValidateException $e) {
            // Rate limit exceeded
            return $this->error('RATE_LIMITED', $e->getMessage(), [], 429);
        }

        return $this->success([
            'job_id'  => $result['id'] ?? null,
            'status'  => $result['status'] ?? 'queued',
            'message' => 'Analytics refresh has been queued.',
        ], 202);
    }

    public function refreshStatus($id, AnalyticsService $analyticsService): Response
    {
        $allowed = ['operations_analyst', 'administrator'];
        if (empty(array_intersect($this->request->roles, $allowed))) {
            return $this->error('FORBIDDEN_ROLE', 'You do not have permission to view refresh status.', [], 403);
        }

        $status = $analyticsService->getRefreshStatus((int)$id);

        if (!$status) {
            return $this->error('NOT_FOUND', 'Refresh request not found.', [], 404);
        }

        // Ownership check: only the request owner or administrator can view status
        $userRoles = $this->request->roles ?? [];
        if (!in_array('administrator', $userRoles, true)) {
            if (((int) ($status['user_id'] ?? 0)) !== $this->request->userId) {
                return $this->error('FORBIDDEN_ROLE', 'You do not have permission to view this refresh status.', [], 403);
            }
        }

        return $this->success($status);
    }
}
