<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ReportRun extends Model
{
    protected $table = 'report_runs';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'            => 'int',
        'definition_id' => 'int',
        'status'        => 'string',
        'artifact_path' => 'string',
        'expires_at'    => 'datetime',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'created_at'    => 'datetime',
    ];
}
