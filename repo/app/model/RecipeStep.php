<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class RecipeStep extends Model
{
    protected $table = 'recipe_steps';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'               => 'int',
        'version_id'       => 'int',
        'step_number'      => 'int',
        'instruction'      => 'string',
        'duration_minutes' => 'int',
        'created_at'       => 'datetime',
    ];
}
