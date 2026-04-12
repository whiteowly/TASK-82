<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class SettlementReversal extends Model
{
    protected $table = 'settlement_reversals';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'                       => 'int',
        'original_statement_id'    => 'int',
        'replacement_statement_id' => 'int',
        'reason'                   => 'string',
        'reversed_by'              => 'int',
        'created_at'               => 'datetime',
    ];
}
