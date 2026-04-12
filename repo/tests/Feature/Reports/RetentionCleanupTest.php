<?php
declare(strict_types=1);

namespace tests\Feature\Reports;

use tests\TestCase;

class RetentionCleanupTest extends TestCase
{
    public function testRetentionCleanupCommandRuns(): void
    {
        exec('cd /app && php think retention:cleanup 2>&1', $output, $exit);
        $this->assertEquals(0, $exit, implode("\n", $output));
        $this->assertStringContainsString('Retention cleanup', implode("\n", $output));
    }

    public function testAuditRetentionNoPrematurePurge(): void
    {
        exec('cd /app && php think audit:retention 2>&1', $output, $exit);
        $this->assertEquals(0, $exit, implode("\n", $output));
        $this->assertStringContainsString('No audit log records will be purged', implode("\n", $output));
    }

    public function testActiveArtifactPreservedAfterCleanup(): void
    {
        $s = $this->loginAs('admin');

        // Create and run a report — this generates a real artifact with expires_at +180 days
        $def = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $s, [
            'name' => 'Active ' . uniqid(),
            'dimensions_json' => ['type' => 'regional_distribution'],
        ]);
        $this->assertContains($def['status'], [200, 201]);

        $run = $this->authenticatedRequest('POST', '/api/v1/reports/definitions/' . $def['body']['data']['id'] . '/run', $s);
        $this->assertContains($run['status'], [200, 202]);
        $runId = $run['body']['data']['run_id'];

        // Get the artifact path
        $detail = $this->authenticatedRequest('GET', '/api/v1/reports/runs/' . $runId, $s);
        $artifactPath = $detail['body']['data']['artifact_path'] ?? '';
        $this->assertNotEmpty($artifactPath, 'Run must have an artifact_path');
        $this->assertFileExists($artifactPath, 'Artifact file must exist on disk');

        // Run cleanup
        exec('cd /app && php think retention:cleanup 2>&1');

        // Active artifact must still exist
        $this->assertFileExists($artifactPath, 'Active artifact must survive cleanup');
        $checkRun = $this->authenticatedRequest('GET', '/api/v1/reports/runs/' . $runId, $s);
        $this->assertNotEquals('expired', $checkRun['body']['data']['status'] ?? '');
    }

    public function testExpiredArtifactDeletedByCleanup(): void
    {
        $s = $this->loginAs('admin');

        // Create and run a report to generate a real artifact file
        $def = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $s, [
            'name' => 'Expiring ' . uniqid(),
            'dimensions_json' => ['type' => 'participation'],
        ]);
        $this->assertContains($def['status'], [200, 201]);

        $run = $this->authenticatedRequest('POST', '/api/v1/reports/definitions/' . $def['body']['data']['id'] . '/run', $s);
        $this->assertContains($run['status'], [200, 202]);
        $runId = $run['body']['data']['run_id'];

        // Get the real artifact file path
        $detail = $this->authenticatedRequest('GET', '/api/v1/reports/runs/' . $runId, $s);
        $artifactPath = $detail['body']['data']['artifact_path'] ?? '';
        $this->assertNotEmpty($artifactPath, 'Run must have artifact_path');
        $this->assertFileExists($artifactPath, 'Artifact file must exist before expiry');

        // Expire the run by setting expires_at to the past
        $phpCmd = "cd /app && php -r \"require 'vendor/autoload.php'; " .
                  "(new think\\\\App())->initialize(); " .
                  "think\\\\facade\\\\Db::name('report_runs')->where('id', {$runId})" .
                  "->update(['expires_at' => '2020-01-01 00:00:00']);\"";
        exec($phpCmd . ' 2>&1', $setOut, $setExit);
        $this->assertEquals(0, $setExit, 'Set expires_at failed: ' . implode("\n", $setOut));

        // Run cleanup
        exec('cd /app && php think retention:cleanup 2>&1', $cleanOut, $cleanExit);
        $this->assertEquals(0, $cleanExit);
        $cleanText = implode("\n", $cleanOut);

        // Assert: the artifact file was deleted from disk
        $this->assertFileDoesNotExist($artifactPath,
            'Expired artifact file must be removed from disk after cleanup. Output: ' . $cleanText);

        // Assert: the run status is now expired
        $check = $this->authenticatedRequest('GET', '/api/v1/reports/runs/' . $runId, $s);
        $this->assertEquals('expired', $check['body']['data']['status'],
            'Run status must be expired after cleanup');
    }
}
