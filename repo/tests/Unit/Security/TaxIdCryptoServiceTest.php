<?php
declare(strict_types=1);

namespace tests\Unit\Security;

use app\service\security\TaxIdEncryptionService;
use tests\TestCase;

class TaxIdCryptoServiceTest extends TestCase
{
    private TaxIdEncryptionService $service;

    protected function setUp(): void
    {
        // Use a test key (32 hex chars = 16 bytes, but we'll use a longer one for AES-256)
        $this->service = new TaxIdEncryptionService('a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2');
    }

    public function testEncryptProducesDifferentOutput(): void
    {
        $taxId = '123-45-6789';
        $encrypted = $this->service->encrypt($taxId);

        $this->assertNotEquals($taxId, $encrypted);
        $this->assertNotEmpty($encrypted);
    }

    public function testDecryptRecoversOriginal(): void
    {
        $taxId = '123-45-6789';
        $encrypted = $this->service->encrypt($taxId);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($taxId, $decrypted);
    }

    public function testSamePlaintextProducesDifferentCiphertexts(): void
    {
        $taxId = '123-45-6789';
        $enc1 = $this->service->encrypt($taxId);
        $enc2 = $this->service->encrypt($taxId);

        // Due to random IV, ciphertexts should differ
        $this->assertNotEquals($enc1, $enc2);
    }

    public function testEmptyStringCanBeEncrypted(): void
    {
        $encrypted = $this->service->encrypt('');
        $decrypted = $this->service->decrypt($encrypted);
        $this->assertEquals('', $decrypted);
    }
}
