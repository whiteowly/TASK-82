<?php
declare(strict_types=1);

namespace app;

use think\Service;

class AppService extends Service
{
    public function register(): void
    {
        // Register service bindings
        $this->app->bind('password_hash', \app\service\auth\PasswordHashService::class);
        $this->app->bind('tax_id_encryption', \app\service\security\TaxIdEncryptionService::class);
        $this->app->bind('field_masking', \app\service\security\FieldMaskingService::class);
        $this->app->bind('audit', \app\service\audit\AuditService::class);
        $this->app->bind('fingerprint', \app\service\file\FingerprintService::class);
        $this->app->bind('file_storage', \app\service\file\FileStorageService::class);
    }

    public function boot(): void
    {
        //
    }
}
