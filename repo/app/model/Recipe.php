<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Recipe extends Model
{
    protected $table = 'recipes';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'                   => 'int',
        'site_id'              => 'int',
        'title'                => 'string',
        'published_version_id' => 'int',
        'status'               => 'string',
        'created_by'           => 'int',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];
}
