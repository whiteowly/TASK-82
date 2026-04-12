<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class UserValidate extends Validate
{
    protected $rule = [
        'username'     => 'require|min:3|max:50',
        'password'     => 'require|min:8',
        'display_name' => 'require|max:100',
    ];

    protected $message = [
        'username.require'     => 'Username is required',
        'username.min'         => 'Username must be at least 3 characters',
        'username.max'         => 'Username must be at most 50 characters',
        'password.require'     => 'Password is required',
        'password.min'         => 'Password must be at least 8 characters',
        'display_name.require' => 'Display name is required',
        'display_name.max'     => 'Display name must be at most 100 characters',
    ];
}
