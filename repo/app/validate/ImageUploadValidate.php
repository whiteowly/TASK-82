<?php
declare(strict_types=1);

namespace app\validate;

use think\Validate;

class ImageUploadValidate extends Validate
{
    protected $rule = [
        'file' => 'require|fileSize:5242880|fileExt:jpg,jpeg,png|fileMime:image/jpeg,image/png',
    ];

    protected $message = [
        'file.require'  => 'File is required',
        'file.fileSize'  => 'File size must not exceed 5MB',
        'file.fileExt'   => 'File extension must be jpg, jpeg, or png',
        'file.fileMime'  => 'File type must be JPEG or PNG',
    ];
}
