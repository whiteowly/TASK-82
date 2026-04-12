<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class SettlementStatement extends Model
{
    protected $table = 'settlement_statements';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'           => 'int',
        'site_id'      => 'int',
        'period'       => 'string',
        'status'       => 'string',
        'total_amount' => 'float',
        'generated_by' => 'int',
        'submitted_by' => 'int',
        'approved_by'  => 'int',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
