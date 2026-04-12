<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class SettlementLine extends Model
{
    protected $table = 'settlement_lines';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'           => 'int',
        'statement_id' => 'int',
        'description'  => 'string',
        'amount'       => 'float',
        'category'     => 'string',
        'created_at'   => 'datetime',
    ];
}
