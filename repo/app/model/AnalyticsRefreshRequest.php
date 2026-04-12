<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AnalyticsRefreshRequest extends Model
{
    protected $table = 'analytics_refresh_requests';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'           => 'int',
        'user_id'      => 'int',
        'site_id'      => 'int',
        'status'       => 'string',
        'created_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];
}
