<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class FreightRule extends Model
{
    protected $table = 'freight_rules';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';

    protected $schema = [
        'id'                => 'int',
        'site_id'           => 'int',
        'name'              => 'string',
        'distance_band_json' => 'string',
        'weight_tiers_json' => 'string',
        'volume_tiers_json' => 'string',
        'surcharges_json'   => 'string',
        'tax_rate'          => 'float',
        'active'            => 'int',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];
}
