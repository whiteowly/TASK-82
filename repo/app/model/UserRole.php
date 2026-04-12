<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class UserRole extends Model
{
    protected $table = 'user_roles';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'role_id'    => 'int',
        'created_at' => 'datetime',
    ];
}
