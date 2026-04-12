<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class RecipeImage extends Model
{
    protected $table = 'recipe_images';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'            => 'int',
        'version_id'    => 'int',
        'file_path'     => 'string',
        'original_name' => 'string',
        'mime_type'     => 'string',
        'file_size'     => 'int',
        'sha256_hash'   => 'string',
        'sort_order'    => 'int',
        'created_at'    => 'datetime',
    ];
}
