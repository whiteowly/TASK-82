<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ReportSchedule extends Model
{
    protected $table = 'report_schedules';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'            => 'int',
        'definition_id' => 'int',
        'cadence'       => 'string',
        'next_run_at'   => 'datetime',
        'active'        => 'int',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];
}
