<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Site extends Model
{
    protected $table = 'sites';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'         => 'int',
        'name'       => 'string',
        'code'       => 'string',
        'address'    => 'string',
        'status'     => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
