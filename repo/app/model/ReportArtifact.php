<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ReportArtifact extends Model
{
    protected $table = 'report_artifacts';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'          => 'int',
        'run_id'      => 'int',
        'file_path'   => 'string',
        'file_size'   => 'int',
        'sha256_hash' => 'string',
        'created_at'  => 'datetime',
    ];
}
