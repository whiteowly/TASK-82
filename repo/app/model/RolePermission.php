<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'            => 'int',
        'role_id'       => 'int',
        'permission_id' => 'int',
    ];
}
