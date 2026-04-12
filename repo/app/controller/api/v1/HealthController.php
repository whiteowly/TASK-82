<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use think\facade\Db;
use think\Response;

class HealthController extends BaseController
{
    /**
     * GET /api/v1/health
     *
     * Returns service health including database connectivity.
     */
    public function index(): Response
    {
        $dbStatus = 'ok';

        try {
            Db::query('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'unavailable';
        }

        return $this->success([
            'status'    => 'ok',
            'timestamp' => date('c'),
            'services'  => [
                'database' => $dbStatus,
            ],
        ]);
    }
}
