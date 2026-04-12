<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ReportDefinition extends Model
{
    protected $table = 'report_definitions';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'              => 'int',
        'name'            => 'string',
        'description'     => 'string',
        'dimensions_json' => 'string',
        'filters_json'    => 'string',
        'columns_json'    => 'string',
        'created_by'      => 'int',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];
}
