<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class MetricSnapshot extends Model
{
    protected $table = 'metric_snapshots';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'              => 'int',
        'site_id'         => 'int',
        'metric_type'     => 'string',
        'dimension_key'   => 'string',
        'dimension_value' => 'string',
        'value'           => 'float',
        'snapshot_date'   => 'datetime',
        'created_at'      => 'datetime',
    ];
}
