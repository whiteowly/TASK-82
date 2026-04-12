<?php
declare(strict_types=1);

namespace tests\Feature\File;

use app\service\file\FileStorageService;
use tests\TestCase;

/**
 * Tests that the FileStorageService stores files in the correct location
 * with randomized names and date-based directory structure.
 */
class UploadPathTest extends TestCase
{
    private FileStorageService $storageService;
    private string $testTmpFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->storageService = new FileStorageService();

        // Create a temporary file to simulate an upload
        $this->testTmpFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($this->testTmpFile, 'test file content for upload path verification');
    }

    protected function tearDown(): void
    {
        // Clean up the temp file if it still exists (it may have been moved by store())
        if (!empty($this->testTmpFile) && file_exists($this->testTmpFile)) {
            @unlink($this->testTmpFile);
        }
        parent::tearDown();
    }

    /**
     * The stored file path should use a randomized name, not the original
     * user-supplied filename.
     */
    public function testStoredFileUsesRandomizedName(): void
    {
        $originalName = 'my_document.txt';

        $result = $this->storageService->store($this->testTmpFile, $originalName, 'text/plain');

        $storedPath = $result['stored_path'];
        $storedFilename = basename($storedPath);

        // The stored filename should NOT be the original user-supplied name
        $this->assertNotEquals($originalName, $storedFilename,
            'Stored filename must not match the user-supplied original name');

        // The stored filename should be a hex string (32 hex chars from 16 random bytes) + extension
        $nameWithoutExt = pathinfo($storedFilename, PATHINFO_FILENAME);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $nameWithoutExt,
            'Stored filename should be a 32-character hex string (randomized)');

        // The extension should be preserved from the original
        $this->assertEquals('txt', pathinfo($storedFilename, PATHINFO_EXTENSION),
            'File extension should be preserved from the original filename');

        // Clean up the stored file
        $fullPath = $this->storageService->getSecurePath($storedPath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * The stored path should include date-based directories (YYYY/MM/DD).
     */
    public function testStoredPathIncludesDateDirectory(): void
    {
        // Create a fresh temp file since the previous test may have consumed ours
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tmpFile, 'date directory test content');

        $result = $this->storageService->store($tmpFile, 'test_image.png', 'image/png');

        $storedPath = $result['stored_path'];
        $today = date('Y/m/d');

        // The stored path should start with today's date directory
        $this->assertStringStartsWith($today, $storedPath,
            "Stored path should start with today's date directory ({$today}). Got: {$storedPath}");

        // Verify the full path resolves under /app/storage/uploads/
        $fullPath = $this->storageService->getSecurePath($storedPath);
        $this->assertStringStartsWith('/app/storage/uploads/', $fullPath,
            'Full path must be under /app/storage/uploads/');
        $this->assertStringContainsString($today, $fullPath,
            'Full path must contain the date-based directory');

        // Clean up
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Even with a potentially dangerous user-supplied filename, the stored
     * path should use a safe randomized name — no path traversal, no
     * user-controlled directory names.
     */
    public function testStoredFileIsNotUserControlledName(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'upload_test_');
        file_put_contents($tmpFile, 'path traversal test content');

        // Attempt to supply a malicious filename with path traversal
        $maliciousName = '../../../etc/passwd.txt';

        $result = $this->storageService->store($tmpFile, $maliciousName, 'text/plain');

        $storedPath = $result['stored_path'];
        $storedFilename = basename($storedPath);

        // The stored path must not contain path traversal sequences
        $this->assertStringNotContainsString('..', $storedPath,
            'Stored path must not contain path traversal sequences');

        // The stored filename must not match the malicious name
        $this->assertNotEquals('passwd.txt', $storedFilename,
            'Stored filename must not be derived from the malicious user input');
        $this->assertStringNotContainsString('passwd', $storedFilename,
            'Stored filename must not contain any part of the malicious name');

        // The filename should still be a randomized hex string
        $nameWithoutExt = pathinfo($storedFilename, PATHINFO_FILENAME);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $nameWithoutExt,
            'Even with malicious input, stored filename should be a 32-char hex string');

        // The original name should be preserved in metadata (for display), not in the path
        $this->assertEquals($maliciousName, $result['original_name'],
            'Original filename should be preserved in metadata for reference');

        // Clean up
        $fullPath = $this->storageService->getSecurePath($storedPath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
