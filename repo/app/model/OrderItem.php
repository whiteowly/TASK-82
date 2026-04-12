<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'         => 'int',
        'order_id'   => 'int',
        'product_id' => 'int',
        'quantity'   => 'int',
        'unit_price' => 'float',
        'subtotal'   => 'float',
    ];
}
