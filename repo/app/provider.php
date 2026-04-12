<?php

// Container bindings: abstract => concrete
return [
    \think\exception\Handle::class => \app\ExceptionHandle::class,
    \think\Request::class          => \app\Request::class,
    'password_hash'     => \app\service\auth\PasswordHashService::class,
    'tax_id_encryption' => \app\service\security\TaxIdEncryptionService::class,
    'field_masking'     => \app\service\security\FieldMaskingService::class,
    'audit'             => \app\service\audit\AuditService::class,
    'fingerprint'       => \app\service\file\FingerprintService::class,
    'file_storage'      => \app\service\file\FileStorageService::class,
];
