<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class RecipeVersion extends Model
{
    protected $table = 'recipe_versions';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'             => 'int',
        'recipe_id'      => 'int',
        'version_number' => 'int',
        'status'         => 'string',
        'content_json'   => 'string',
        'prep_time'      => 'int',
        'cook_time'      => 'int',
        'total_time'     => 'int',
        'difficulty'     => 'string',
        'reviewer_id'    => 'int',
        'reviewed_at'    => 'datetime',
        'created_by'     => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}
