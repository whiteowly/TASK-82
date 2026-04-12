<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Community extends Model
{
    protected $table = 'communities';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'         => 'int',
        'site_id'    => 'int',
        'name'       => 'string',
        'status'     => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
