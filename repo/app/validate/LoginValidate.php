<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class LoginValidate extends Validate
{
    protected $rule = [
        'username' => 'require',
        'password' => 'require',
    ];

    protected $message = [
        'username.require' => 'Username is required',
        'password.require' => 'Password is required',
    ];
}
