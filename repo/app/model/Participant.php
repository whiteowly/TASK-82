<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Participant extends Model
{
    protected $table = 'participants';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'          => 'int',
        'site_id'     => 'int',
        'name'        => 'string',
        'phone'       => 'string',
        'company_id'  => 'int',
        'position_id' => 'int',
        'status'      => 'string',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
