<?php
declare(strict_types=1);

namespace tests\Feature\Reports;

use app\service\security\FieldMaskingService;
use tests\TestCase;

/**
 * Proves CSV export masking through the actual endpoint response.
 * Seeded data includes participants with phone numbers, so the export
 * should contain real rows.
 */
class CsvMaskingTest extends TestCase
{
    public function testAnalystCsvExportMasksPhoneNumbers(): void
    {
        $s = $this->loginAs('analyst');

        // Export participants — seed data has 12 participants with phones
        $response = $this->authenticatedRequest('POST', '/api/v1/exports/csv', $s, [
            'type' => 'participants',
            'filters' => [],
        ]);

        // The endpoint must return 200 with CSV content (seed data guarantees participants exist)
        $this->assertEquals(200, $response['status'],
            'Participants CSV export must succeed for analyst. Got: ' . ($response['raw'] ?? ''));

        $csv = $response['raw'];
        $this->assertNotEmpty($csv);

        // CSV should contain the "phone" column header
        $this->assertStringContainsString('phone', $csv, 'CSV must include phone column');

        // Parse CSV rows and check that phone values contain masking asterisks
        $lines = explode("\n", trim($csv));
        $this->assertGreaterThan(1, count($lines), 'CSV must have header + data rows');

        $header = str_getcsv($lines[0]);
        $phoneIdx = array_search('phone', $header);
        $this->assertNotFalse($phoneIdx, 'phone column must be in header');

        // Check at least one data row has masked phone (contains *)
        $hasMasked = false;
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            if (isset($row[$phoneIdx]) && str_contains($row[$phoneIdx], '*')) {
                $hasMasked = true;
                break;
            }
        }
        $this->assertTrue($hasMasked, 'Analyst CSV export must contain masked phone values (with *)');
    }

    public function testAdminCsvExportShowsUnmaskedPhones(): void
    {
        $s = $this->loginAs('admin');

        $response = $this->authenticatedRequest('POST', '/api/v1/exports/csv', $s, [
            'type' => 'participants',
            'filters' => [],
        ]);

        $this->assertEquals(200, $response['status'],
            'Participants CSV export must succeed for admin. Got: ' . ($response['raw'] ?? ''));

        $csv = $response['raw'];
        $lines = explode("\n", trim($csv));
        $header = str_getcsv($lines[0]);
        $phoneIdx = array_search('phone', $header);
        $this->assertNotFalse($phoneIdx);

        // Admin should see real phone numbers without asterisks
        $hasUnmasked = false;
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            if (isset($row[$phoneIdx]) && !empty($row[$phoneIdx]) && !str_contains($row[$phoneIdx], '*')) {
                $hasUnmasked = true;
                break;
            }
        }
        $this->assertTrue($hasUnmasked, 'Admin CSV export must contain unmasked phone values');
    }

    public function testMaskingServicePolicyIsCorrect(): void
    {
        $svc = new FieldMaskingService();
        // Editor: phone and tax_id masked
        $this->assertTrue($svc->shouldMask('phone', ['content_editor']));
        $this->assertTrue($svc->shouldMask('tax_id', ['content_editor']));
        // Admin: nothing masked except password_hash
        $this->assertFalse($svc->shouldMask('phone', ['administrator']));
        $this->assertFalse($svc->shouldMask('tax_id', ['administrator']));
        $this->assertTrue($svc->shouldMask('password_hash', ['administrator']));
    }
}
