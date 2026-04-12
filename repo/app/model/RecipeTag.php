<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class RecipeTag extends Model
{
    protected $table = 'recipe_tags';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'         => 'int',
        'name'       => 'string',
        'created_at' => 'datetime',
    ];
}
