<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class FreightRuleValidate extends Validate
{
    protected $rule = [
        'name'     => 'require|max:200',
        'site_id'  => 'require|integer',
        'tax_rate' => 'require|float|egt:0',
    ];

    protected $message = [
        'name.require'     => 'Name is required',
        'name.max'         => 'Name must be at most 200 characters',
        'site_id.require'  => 'Site ID is required',
        'site_id.integer'  => 'Site ID must be an integer',
        'tax_rate.require' => 'Tax rate is required',
        'tax_rate.float'   => 'Tax rate must be a number',
        'tax_rate.egt'     => 'Tax rate must be greater than or equal to 0',
    ];
}
