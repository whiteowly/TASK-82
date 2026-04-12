<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class User extends Model
{
    protected $table = 'users';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $hidden = ['password_hash'];

    protected $schema = [
        'id'            => 'int',
        'username'      => 'string',
        'password_hash' => 'string',
        'display_name'  => 'string',
        'status'        => 'string',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];
}
