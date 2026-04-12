<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ReviewAction extends Model
{
    protected $table = 'review_actions';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'          => 'int',
        'version_id'  => 'int',
        'reviewer_id' => 'int',
        'action'      => 'string',
        'comment'     => 'string',
        'created_at'  => 'datetime',
    ];
}
