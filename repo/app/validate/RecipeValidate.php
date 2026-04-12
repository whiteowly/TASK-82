<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class RecipeValidate extends Validate
{
    protected $rule = [
        'title'   => 'require|max:200',
        'site_id' => 'require|integer',
    ];

    protected $message = [
        'title.require'   => 'Title is required',
        'title.max'       => 'Title must be at most 200 characters',
        'site_id.require' => 'Site ID is required',
        'site_id.integer' => 'Site ID must be an integer',
    ];

    protected $scene = [
        'create' => ['title', 'site_id'],
    ];
}
