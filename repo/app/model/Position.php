<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Position extends Model
{
    protected $table = 'positions';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'         => 'int',
        'name'       => 'string',
        'department' => 'string',
        'created_at' => 'datetime',
    ];
}
