<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class SettlementApproval extends Model
{
    protected $table = 'settlement_approvals';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'           => 'int',
        'statement_id' => 'int',
        'actor_id'     => 'int',
        'action'       => 'string',
        'comment'      => 'string',
        'created_at'   => 'datetime',
    ];
}
