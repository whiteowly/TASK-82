<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'              => 'int',
        'site_id'         => 'int',
        'participant_id'  => 'int',
        'group_leader_id' => 'int',
        'total_amount'    => 'float',
        'status'          => 'string',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];
}
