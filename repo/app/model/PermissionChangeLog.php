<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class PermissionChangeLog extends Model
{
    protected $table = 'permission_change_logs';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'             => 'int',
        'actor_id'       => 'int',
        'target_user_id' => 'int',
        'change_type'    => 'string',
        'old_value'      => 'string',
        'new_value'      => 'string',
        'request_id'     => 'string',
        'created_at'     => 'datetime',
    ];
}
