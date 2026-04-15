<?php
declare(strict_types=1);

namespace tests\Feature\Reports;

use tests\TestCase;

/**
 * Tests for GET /api/v1/reports/runs/:id/download — proves the
 * ReportController::download handler executes through the live HTTP route
 * and streams a downloadable artifact (status + key headers + body).
 *
 * ZERO mocks/stubs.
 */
class ReportDownloadTest extends TestCase
{
    private array $analystSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analystSession = $this->loginAs('analyst');
    }

    /**
     * Issue an authenticated GET that captures both response headers and the
     * raw body. The shared helper in TestCase only returns the parsed JSON body,
     * but a download response needs header inspection (Content-Disposition).
     *
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    private function downloadRequest(string $path, array $session): array
    {
        $url = 'http://127.0.0.1:8080' . $path;

        $headersOut = [];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $reqHeaders = ['Accept: */*'];
        if (!empty($session['cookie'])) {
            $reqHeaders[] = 'Cookie: ' . $session['cookie'];
        }
        if (!empty($session['access_token'])) {
            $reqHeaders[] = 'Authorization: Bearer ' . $session['access_token'];
        }
        if (!empty($session['csrf_token'])) {
            $reqHeaders[] = 'X-CSRF-Token: ' . $session['csrf_token'];
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $headerLine) use (&$headersOut) {
            $colon = strpos($headerLine, ':');
            if ($colon !== false) {
                $name  = strtolower(trim(substr($headerLine, 0, $colon)));
                $value = trim(substr($headerLine, $colon + 1));
                $headersOut[$name] = $value;
            }
            return strlen($headerLine);
        });

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status'  => $httpCode,
            'headers' => $headersOut,
            'body'    => (string) ($body ?: ''),
        ];
    }

    public function testDownloadReturnsArtifactAttachment(): void
    {
        // Step 1: Create a definition as the analyst.
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Download Test Report ' . uniqid(),
            'description' => 'Generates an artifact for download verification',
            'dimensions'  => ['type' => 'participation'],
        ]);
        $this->assertEquals(201, $createResponse['status'],
            'Definition creation must succeed. Got: ' . ($createResponse['raw'] ?? ''));
        $defId = (int) ($createResponse['body']['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $defId);

        // Step 2: Run the definition. The service generates the artifact synchronously.
        $runResponse = $this->authenticatedRequest(
            'POST',
            "/api/v1/reports/definitions/{$defId}/run",
            $this->analystSession
        );
        $this->assertContains($runResponse['status'], [200, 202],
            'Run execution must succeed. Got: ' . ($runResponse['raw'] ?? ''));
        $runId = (int) ($runResponse['body']['data']['run_id'] ?? 0);
        $this->assertGreaterThan(0, $runId, 'Run id must be returned');

        // Step 3: Download the artifact through the real route.
        $download = $this->downloadRequest("/api/v1/reports/runs/{$runId}/download", $this->analystSession);

        $this->assertEquals(200, $download['status'],
            'Download endpoint must return 200 for a completed run. Got body: ' . $download['body']);

        // Header-level evidence the handler streamed an attachment, not a JSON envelope.
        $disposition = $download['headers']['content-disposition'] ?? '';
        $this->assertNotSame('', $disposition,
            'Download response must include a Content-Disposition header.');
        $this->assertStringContainsString('attachment', strtolower($disposition),
            'Content-Disposition must mark the response as an attachment.');

        // Body should be the raw artifact (non-empty), not an error envelope.
        $this->assertNotSame('', $download['body'], 'Download body must not be empty.');
        $decoded = json_decode($download['body'], true);
        $this->assertTrue(
            !is_array($decoded) || !isset($decoded['error']),
            'Download response must not be a JSON error envelope.'
        );
    }

    public function testDownloadUnknownRunReturns404(): void
    {
        $download = $this->downloadRequest('/api/v1/reports/runs/9999999/download', $this->analystSession);

        $this->assertEquals(404, $download['status']);
        $decoded = json_decode($download['body'], true) ?: [];
        $this->assertSame('NOT_FOUND', $decoded['error']['code'] ?? '',
            'Unknown run should produce a NOT_FOUND error envelope from the handler.');
    }
}
