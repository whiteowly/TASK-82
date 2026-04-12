<?php
declare(strict_types=1);

namespace tests\Unit\File;

use app\service\file\FingerprintService;
use tests\TestCase;

class ShaFingerprintServiceTest extends TestCase
{
    private FingerprintService $service;

    protected function setUp(): void
    {
        $this->service = new FingerprintService();
    }

    public function testComputeReturnsSha256Hash(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content for hashing');

        $hash = $this->service->compute($tmpFile);

        $this->assertNotEmpty($hash);
        $this->assertEquals(64, strlen($hash), 'SHA-256 hash should be 64 hex characters');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);

        unlink($tmpFile);
    }

    public function testSameContentProducesSameHash(): void
    {
        $content = 'identical content';

        $file1 = tempnam(sys_get_temp_dir(), 'test1_');
        $file2 = tempnam(sys_get_temp_dir(), 'test2_');
        file_put_contents($file1, $content);
        file_put_contents($file2, $content);

        $hash1 = $this->service->compute($file1);
        $hash2 = $this->service->compute($file2);

        $this->assertEquals($hash1, $hash2);

        unlink($file1);
        unlink($file2);
    }

    public function testDifferentContentProducesDifferentHash(): void
    {
        $file1 = tempnam(sys_get_temp_dir(), 'test1_');
        $file2 = tempnam(sys_get_temp_dir(), 'test2_');
        file_put_contents($file1, 'content A');
        file_put_contents($file2, 'content B');

        $hash1 = $this->service->compute($file1);
        $hash2 = $this->service->compute($file2);

        $this->assertNotEquals($hash1, $hash2);

        unlink($file1);
        unlink($file2);
    }
}
