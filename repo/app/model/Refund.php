<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Refund extends Model
{
    protected $table = 'refunds';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'         => 'int',
        'order_id'   => 'int',
        'amount'     => 'float',
        'reason'     => 'string',
        'status'     => 'string',
        'created_at' => 'datetime',
    ];
}
