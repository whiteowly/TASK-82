<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Permission extends Model
{
    protected $table = 'permissions';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'           => 'int',
        'name'         => 'string',
        'display_name' => 'string',
        'module'       => 'string',
        'created_at'   => 'datetime',
    ];
}
