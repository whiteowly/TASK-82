<?php
declare(strict_types=1);

namespace tests\Unit\Security;

use app\service\auth\PasswordHashService;
use tests\TestCase;

class PasswordHashServiceTest extends TestCase
{
    private PasswordHashService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordHashService();
    }

    public function testHashProducesNonReversibleOutput(): void
    {
        $password = 'SecurePassword123!';
        $hash = $this->service->hash($password);

        $this->assertNotEquals($password, $hash);
        $this->assertNotEmpty($hash);
    }

    public function testVerifyReturnsTrueForCorrectPassword(): void
    {
        $password = 'SecurePassword123!';
        $hash = $this->service->hash($password);

        $this->assertTrue($this->service->verify($password, $hash));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $password = 'SecurePassword123!';
        $hash = $this->service->hash($password);

        $this->assertFalse($this->service->verify('WrongPassword', $hash));
    }

    public function testDifferentPasswordsProduceDifferentHashes(): void
    {
        $hash1 = $this->service->hash('password1');
        $hash2 = $this->service->hash('password2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testHashUsesArgon2idOrBcrypt(): void
    {
        $hash = $this->service->hash('TestPassword');

        // Should start with $argon2id$ or $2y$ (bcrypt fallback)
        $this->assertTrue(
            str_starts_with($hash, '$argon2id$') || str_starts_with($hash, '$2y$'),
            'Hash should use Argon2id or bcrypt'
        );
    }
}
