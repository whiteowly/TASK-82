<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'         => 'int',
        'site_id'    => 'int',
        'name'       => 'string',
        'category'   => 'string',
        'unit'       => 'string',
        'price'      => 'float',
        'status'     => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
