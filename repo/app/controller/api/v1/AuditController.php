<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\service\audit\AuditService;
use think\facade\Db;
use think\Response;

class AuditController extends BaseController
{
    public function logs(AuditService $auditService): Response
    {
        $filters = [];
        if ($v = $this->request->get('event_type')) $filters['event_type'] = $v;
        if ($v = $this->request->get('site'))       $filters['site_id'] = (int)$v;
        if ($v = $this->request->get('actor'))      $filters['actor_id'] = (int)$v;
        if ($v = $this->request->get('target_type')) $filters['target_type'] = $v;

        $entries = $auditService->query($filters);

        return $this->success([
            'items' => $entries,
            'total' => count($entries),
        ]);
    }

    public function logDetail($id, AuditService $auditService): Response
    {
        $entry = $auditService->findEntry((int)$id);
        if (!$entry) {
            return $this->error('NOT_FOUND', 'Audit log entry not found.', [], 404);
        }
        return $this->success($entry);
    }

    public function exports(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        $perPage = min(100, max(1, (int)$this->request->get('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $query = Db::name('export_logs')->order('created_at', 'desc');
        if ($v = $this->request->get('site_id')) {
            $query->where('site_id', (int)$v);
        }

        $total = (clone $query)->count();
        $items = $query->limit($offset, $perPage)->select()->toArray();

        return $this->success([
            'items' => $items,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    public function approvals(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        $perPage = min(100, max(1, (int)$this->request->get('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $query = Db::name('settlement_approvals')->order('created_at', 'desc');
        if ($v = $this->request->get('statement_id')) {
            $query->where('statement_id', (int)$v);
        }

        $total = (clone $query)->count();
        $items = $query->limit($offset, $perPage)->select()->toArray();

        return $this->success([
            'items' => $items,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    public function permissionChanges(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        $perPage = min(100, max(1, (int)$this->request->get('per_page', 50)));
        $offset = ($page - 1) * $perPage;

        $query = Db::name('permission_change_logs')->order('created_at', 'desc');
        if ($v = $this->request->get('target_user_id')) {
            $query->where('target_user_id', (int)$v);
        }

        $total = (clone $query)->count();
        $items = $query->limit($offset, $perPage)->select()->toArray();

        return $this->success([
            'items' => $items,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }
}
