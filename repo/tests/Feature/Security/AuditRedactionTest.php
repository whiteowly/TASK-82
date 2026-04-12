<?php
declare(strict_types=1);

namespace tests\Feature\Security;

use tests\TestCase;

/**
 * Verify that audit log payloads and error responses do not leak
 * sensitive values such as passwords, tax IDs, or encryption keys.
 */
class AuditRedactionTest extends TestCase
{
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminSession = $this->loginAs('admin');
        $this->assertNotEmpty($this->adminSession['csrf_token'], 'Admin login must succeed');
    }

    /**
     * After a settlement submit action the resulting audit log entry's
     * payload_summary must not contain any password or password_hash value.
     */
    public function testAuditPayloadDoesNotContainPasswords(): void
    {
        // Generate and submit a settlement to create an audit entry
        $statementId = $this->createAndSubmitSettlement();

        // Query audit logs for the settlement.submit event
        $response = $this->authenticatedRequest('GET', '/api/v1/audit/logs?event_type=settlement.submit', $this->adminSession);
        $this->assertEquals(200, $response['status'], 'Audit log query must succeed');

        $items = $response['body']['data']['items'] ?? [];
        $this->assertNotEmpty($items, 'There should be at least one settlement.submit audit entry');

        $password = bootstrap_config('seed_admin_password', '');

        foreach ($items as $entry) {
            $summary = $entry['payload_summary'] ?? '';
            $this->assertStringNotContainsString($password, $summary,
                'Audit payload_summary must not contain the raw password');
            $this->assertStringNotContainsString('password_hash', $summary,
                'Audit payload_summary must not reference password_hash');
            $this->assertStringNotContainsString('$2y$', $summary,
                'Audit payload_summary must not contain bcrypt hashes');
            $this->assertStringNotContainsString('$argon2', $summary,
                'Audit payload_summary must not contain argon2 hashes');
        }
    }

    /**
     * Audit log payload_summary must not contain raw tax IDs.
     */
    public function testAuditPayloadDoesNotContainTaxIds(): void
    {
        // Create a settlement event to get audit entries
        $this->createAndSubmitSettlement();

        $response = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $this->adminSession);
        $this->assertEquals(200, $response['status']);

        $items = $response['body']['data']['items'] ?? [];
        $this->assertNotEmpty($items, 'Audit log should contain entries');

        // Tax ID patterns: SSN-like (XXX-XX-XXXX) or EIN-like (XX-XXXXXXX)
        $taxIdPatterns = [
            '/\b\d{3}-\d{2}-\d{4}\b/',   // SSN format
            '/\b\d{2}-\d{7}\b/',           // EIN format
        ];

        foreach ($items as $entry) {
            $summary = $entry['payload_summary'] ?? '';
            foreach ($taxIdPatterns as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $summary,
                    'Audit payload_summary must not contain raw tax IDs. Entry ID: ' . ($entry['id'] ?? 'unknown'));
            }
        }
    }

    /**
     * Error responses for invalid login attempts must not leak password hashes.
     */
    public function testErrorResponseDoesNotLeakPasswordHash(): void
    {
        // Attempt login with invalid credentials
        $response = $this->httpRequest('POST', '/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'completely_wrong_password_12345',
        ]);

        $this->assertEquals(401, $response['status'], 'Invalid login should return 401');

        $raw = $response['raw'] ?? '';
        $body = $response['body'] ?? [];

        // The raw response must not contain any password hash patterns
        $this->assertStringNotContainsString('$2y$', $raw,
            'Error response must not contain bcrypt password hashes');
        $this->assertStringNotContainsString('$argon2', $raw,
            'Error response must not contain argon2 password hashes');
        $this->assertStringNotContainsString('password_hash', $raw,
            'Error response must not reference password_hash field');

        // The error body should have a generic message, not internals
        $errorMessage = $body['error']['message'] ?? '';
        $this->assertStringNotContainsString('$2y$', $errorMessage);
        $this->assertStringNotContainsString('hash', strtolower($errorMessage),
            'Error message should not reference hashing internals');
    }

    /**
     * Audit log entries must not contain encryption keys.
     */
    public function testAuditLogDoesNotContainEncryptionKeys(): void
    {
        // Trigger activity to populate audit logs
        $this->createAndSubmitSettlement();

        $response = $this->authenticatedRequest('GET', '/api/v1/audit/logs', $this->adminSession);
        $this->assertEquals(200, $response['status']);

        $items = $response['body']['data']['items'] ?? [];
        $this->assertNotEmpty($items, 'Audit log should contain entries');

        $encryptionKey = bootstrap_config('tax_id_encryption_key', '');

        foreach ($items as $entry) {
            $summary = $entry['payload_summary'] ?? '';
            $fullEntry = json_encode($entry);

            if (!empty($encryptionKey)) {
                $this->assertStringNotContainsString($encryptionKey, $summary,
                    'Audit payload_summary must not contain raw encryption keys');
                $this->assertStringNotContainsString($encryptionKey, $fullEntry,
                    'Audit log entry must not contain raw encryption keys');
            }

            // Check for common key patterns that should never appear
            $this->assertStringNotContainsString('encryption_key', $summary,
                'Audit payload_summary must not reference encryption_key');
            $this->assertStringNotContainsString('secret_key', $summary,
                'Audit payload_summary must not reference secret_key');
            $this->assertStringNotContainsString('private_key', $summary,
                'Audit payload_summary must not reference private_key');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createAndSubmitSettlement(): int
    {
        $financeSession = $this->loginAs('finance');
        $siteId = $financeSession['user']['site_scopes'][0] ?? 1;
        $period = '2026-' . str_pad((string) rand(1, 12), 2, '0', STR_PAD_LEFT);

        $genResponse = $this->authenticatedRequest('POST', '/api/v1/finance/settlements/generate', $this->adminSession, [
            'site_id' => $siteId,
            'period'  => $period,
        ]);
        $this->assertContains($genResponse['status'], [200, 202],
            'Settlement generation must succeed. Got: ' . ($genResponse['raw'] ?? ''));

        $statementId = (int) ($genResponse['body']['data']['settlement_id'] ?? 0);
        $this->assertGreaterThan(0, $statementId);

        $submitResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/finance/settlements/{$statementId}/submit",
            $financeSession
        );
        $this->assertEquals(200, $submitResponse['status'],
            'Settlement submit must succeed. Got: ' . ($submitResponse['raw'] ?? ''));

        return $statementId;
    }
}
