<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Company extends Model
{
    protected $table = 'companies';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'               => 'int',
        'name'             => 'string',
        'tax_id_encrypted' => 'string',
        'address'          => 'string',
        'status'           => 'string',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];
}
