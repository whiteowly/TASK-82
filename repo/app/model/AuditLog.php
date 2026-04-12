<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;

    protected $schema = [
        'id'              => 'int',
        'actor_id'        => 'int',
        'actor_role'      => 'string',
        'site_id'         => 'int',
        'target_type'     => 'string',
        'target_id'       => 'int',
        'event_type'      => 'string',
        'request_id'      => 'string',
        'payload_summary' => 'string',
        'prev_hash'       => 'string',
        'entry_hash'      => 'string',
        'created_at'      => 'datetime',
    ];
}
