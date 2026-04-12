<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class RecipeVersionTag extends Model
{
    protected $table = 'recipe_version_tags';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'         => 'int',
        'version_id' => 'int',
        'tag_id'     => 'int',
    ];
}
