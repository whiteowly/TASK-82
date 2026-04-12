<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ReviewComment extends Model
{
    protected $table = 'review_comments';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'          => 'int',
        'version_id'  => 'int',
        'author_id'   => 'int',
        'anchor_type' => 'string',
        'anchor_ref'  => 'string',
        'content'     => 'string',
        'created_at'  => 'datetime',
    ];
}
