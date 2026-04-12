<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ExportLog extends Model
{
    protected $table = 'export_logs';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'           => 'int',
        'actor_id'     => 'int',
        'site_id'      => 'int',
        'export_type'  => 'string',
        'record_count' => 'int',
        'reason'       => 'string',
        'request_id'   => 'string',
        'created_at'   => 'datetime',
    ];
}
