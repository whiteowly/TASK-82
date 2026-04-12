<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GroupLeader extends Model
{
    protected $table = 'group_leaders';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'           => 'int',
        'site_id'      => 'int',
        'community_id' => 'int',
        'name'         => 'string',
        'phone'        => 'string',
        'status'       => 'string',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
