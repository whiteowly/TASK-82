<?php
declare(strict_types=1);

namespace tests\Feature\Security;

use tests\TestCase;

/**
 * Tests for the 7 audit remediation items.
 */
class AuditRemediation7Test extends TestCase
{
    // === Issue 1: File upload authz ===

    public function testUploadDeniedForDisallowedRole(): void
    {
        // finance_clerk is NOT in the upload allowlist
        // Create a recipe as editor first so a version is guaranteed to exist
        $editor = $this->loginAs('editor');
        $versionId = $this->createRecipeVersion($editor);

        $s = $this->loginAs('finance');
        $result = $this->doUpload($s, $versionId);
        $this->assertEquals(403, $result['status'],
            'Finance role should be denied upload. Got: ' . ($result['raw'] ?? ''));
    }

    public function testUploadAllowedForEditor(): void
    {
        $s = $this->loginAs('editor');
        $versionId = $this->createRecipeVersion($s);

        $result = $this->doUpload($s, $versionId);
        $this->assertContains($result['status'], [200, 201],
            'Editor should be allowed upload. Got: ' . ($result['raw'] ?? ''));
    }

    public function testUploadDeniedForCrossSiteVersion(): void
    {
        // Admin creates a recipe in a site outside the editor's scope
        $admin = $this->loginAs('admin');
        // Create recipe in site 3 (editor only has access to sites 1,2)
        $cr = $this->authenticatedRequest('POST', '/api/v1/recipes', $admin, [
            'title' => 'Cross-site test ' . uniqid(), 'site_id' => 3,
        ]);
        $this->assertEquals(201, $cr['status'], 'Admin should create recipe in site 3');
        $rd = $this->authenticatedRequest('GET', '/api/v1/recipes/' . $cr['body']['data']['id'], $admin);
        $crossSiteVersionId = (int) $rd['body']['data']['versions'][0]['id'];

        // Editor (sites 1,2) tries to upload to the site-3 version
        $editor = $this->loginAs('editor');
        $result = $this->doUpload($editor, $crossSiteVersionId);
        $this->assertEquals(403, $result['status'],
            'Cross-site upload should be denied with FORBIDDEN_SITE_SCOPE. Got: ' . ($result['raw'] ?? ''));
    }

    // === Issue 2: Settlement read role guards ===

    public function testEditorDeniedListFreightRules(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('GET', '/api/v1/finance/freight-rules', $s);
        $this->assertEquals(403, $r['status']);
    }

    public function testEditorDeniedListStatements(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('GET', '/api/v1/finance/settlements', $s);
        $this->assertEquals(403, $r['status']);
    }

    public function testEditorDeniedReadStatement(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('GET', '/api/v1/finance/settlements/1', $s);
        $this->assertEquals(403, $r['status']);
    }

    public function testFinanceAllowedListFreightRules(): void
    {
        $s = $this->loginAs('finance');
        $r = $this->authenticatedRequest('GET', '/api/v1/finance/freight-rules', $s);
        $this->assertEquals(200, $r['status']);
    }

    public function testAuditorAllowedListStatements(): void
    {
        $s = $this->loginAs('auditor');
        $r = $this->authenticatedRequest('GET', '/api/v1/finance/settlements', $s);
        $this->assertEquals(200, $r['status']);
    }

    // Search role guard
    public function testEditorDeniedSearch(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('POST', '/api/v1/search/query', $s, ['q' => 'test']);
        $this->assertEquals(403, $r['status']);
    }

    public function testAnalystAllowedSearch(): void
    {
        $s = $this->loginAs('analyst');
        $r = $this->authenticatedRequest('POST', '/api/v1/search/query', $s, ['q' => 'test']);
        $this->assertEquals(200, $r['status']);
    }

    // === Issue 3: /auth/me site scopes ===

    public function testMeReturnsNonEmptySiteScopes(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('GET', '/api/v1/auth/me', $s);
        $this->assertEquals(200, $r['status']);
        $scopes = $r['body']['data']['site_scopes'] ?? [];
        $this->assertNotEmpty($scopes, 'Non-admin user should have non-empty site_scopes');
    }

    // === Issue 4: Ingredient validation ===

    public function testInvalidIngredientUnitReturns422(): void
    {
        $s = $this->loginAs('editor');
        $versionId = $this->createRecipeVersion($s);

        $r = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $s, [
            'total_time' => 30,
            'ingredients' => [
                ['name' => 'Sugar', 'quantity' => 100, 'unit' => 'cups'], // invalid unit
            ],
        ]);
        $this->assertEquals(422, $r['status'], 'Invalid unit: ' . ($r['raw'] ?? ''));
    }

    public function testInvalidIngredientQuantityReturns422(): void
    {
        $s = $this->loginAs('editor');
        $versionId = $this->createRecipeVersion($s);

        $r = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $s, [
            'total_time' => 30,
            'ingredients' => [
                ['name' => 'Salt', 'quantity' => 0, 'unit' => 'g'], // quantity must be > 0
            ],
        ]);
        $this->assertEquals(422, $r['status'], 'Zero qty: ' . ($r['raw'] ?? ''));
    }

    public function testValidIngredientsPass(): void
    {
        $s = $this->loginAs('editor');
        $versionId = $this->createRecipeVersion($s);

        $r = $this->authenticatedRequest('PUT', "/api/v1/recipe-versions/{$versionId}", $s, [
            'total_time' => 30,
            'ingredients' => [
                ['name' => 'Flour', 'quantity' => 500, 'unit' => 'g'],
                ['name' => 'Butter', 'quantity' => 0.5, 'unit' => 'kg'],
            ],
        ]);
        $this->assertEquals(200, $r['status'], 'Valid ingredients: ' . ($r['raw'] ?? ''));
    }

    // === Issue 6: Report runs response shape ===

    public function testListRunsReturnsItemsArray(): void
    {
        $s = $this->loginAs('analyst');
        $r = $this->authenticatedRequest('GET', '/api/v1/reports/runs', $s);
        $this->assertEquals(200, $r['status']);
        $this->assertArrayHasKey('items', $r['body']['data']);
        $this->assertIsArray($r['body']['data']['items']);
        $this->assertArrayHasKey('pagination', $r['body']['data']);
    }

    // === Issue 7: RBAC metadata admin-only ===

    public function testEditorDeniedRbacRoles(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('GET', '/api/v1/rbac/roles', $s);
        $this->assertEquals(403, $r['status']);
    }

    public function testEditorDeniedRbacPermissions(): void
    {
        $s = $this->loginAs('editor');
        $r = $this->authenticatedRequest('GET', '/api/v1/rbac/permissions', $s);
        $this->assertEquals(403, $r['status']);
    }

    public function testAdminAllowedRbacRoles(): void
    {
        $s = $this->loginAs('admin');
        $r = $this->authenticatedRequest('GET', '/api/v1/rbac/roles', $s);
        $this->assertEquals(200, $r['status']);
    }

    // === Helpers ===

    private function createRecipeVersion(array $session): int
    {
        $siteId = $session['user']['site_scopes'][0] ?? 1;
        $cr = $this->authenticatedRequest('POST', '/api/v1/recipes', $session, [
            'title' => 'Remediation Test ' . uniqid(), 'site_id' => $siteId,
        ]);
        $this->assertEquals(201, $cr['status']);
        $rd = $this->authenticatedRequest('GET', '/api/v1/recipes/' . $cr['body']['data']['id'], $session);
        return (int)$rd['body']['data']['versions'][0]['id'];
    }

    private function getAnyVersionId(array $session): ?int
    {
        $r = $this->authenticatedRequest('GET', '/api/v1/recipes', $session);
        $items = $r['body']['data']['items'] ?? [];
        if (empty($items)) return null;
        $rd = $this->authenticatedRequest('GET', '/api/v1/recipes/' . $items[0]['id'], $session);
        $versions = $rd['body']['data']['versions'] ?? [];
        return $versions ? (int)$versions[0]['id'] : null;
    }

    private function doUpload(array $session, int $versionId): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upload_');
        $img = imagecreatetruecolor(5, 5);
        $color = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
        imagefill($img, 0, 0, $color);
        imagepng($img, $tmp);
        imagedestroy($img);

        $ch = curl_init('http://127.0.0.1:8080/api/v1/files/images');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $session['cookie_file']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $session['cookie_file']);
        $headers = ['Accept: application/json'];
        if (!empty($session['csrf_token'])) $headers[] = 'X-CSRF-Token: ' . $session['csrf_token'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new \CURLFile($tmp, 'image/png', 'test.png'),
            'version_id' => (string)$versionId,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @unlink($tmp);
        return ['status' => $code, 'body' => json_decode($response ?: '', true) ?? [], 'raw' => $response];
    }
}
