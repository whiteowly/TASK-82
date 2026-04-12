<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ReconciliationVariance extends Model
{
    protected $table = 'reconciliation_variances';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'             => 'int',
        'statement_id'   => 'int',
        'field_name'     => 'string',
        'expected_value' => 'string',
        'actual_value'   => 'string',
        'notes'          => 'string',
        'resolved'       => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}
