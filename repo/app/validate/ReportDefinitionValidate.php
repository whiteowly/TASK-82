<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class ReportDefinitionValidate extends Validate
{
    protected $rule = [
        'name' => 'require|max:200',
    ];

    protected $message = [
        'name.require' => 'Name is required',
        'name.max'     => 'Name must be at most 200 characters',
    ];
}
