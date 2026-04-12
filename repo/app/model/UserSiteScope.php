<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class UserSiteScope extends Model
{
    protected $table = 'user_site_scopes';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'site_id'    => 'int',
        'created_at' => 'datetime',
    ];
}
