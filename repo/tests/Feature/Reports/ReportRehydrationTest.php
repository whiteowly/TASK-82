<?php
declare(strict_types=1);

namespace tests\Feature\Reports;

use tests\TestCase;

/**
 * Tests that report definition read returns decoded JSON fields
 * alongside the raw *_json columns for frontend consumption.
 *
 * ZERO markTestSkipped / markTestIncomplete calls.
 */
class ReportRehydrationTest extends TestCase
{
    private array $analystSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analystSession = $this->loginAs('analyst');
    }

    public function testDefinitionReadIncludesDecodedFields(): void
    {
        $dimensions = ['type' => 'participation', 'group_by' => 'site'];
        $filters = ['date_from' => '2026-01-01', 'date_to' => '2026-03-31'];
        $columns = ['site_id', 'participant_count'];

        // Create a definition with dimensions, filters, columns
        $createResponse = $this->authenticatedRequest('POST', '/api/v1/reports/definitions', $this->analystSession, [
            'name'        => 'Rehydration Test ' . uniqid(),
            'description' => 'Testing JSON field rehydration',
            'dimensions'  => $dimensions,
            'filters'     => $filters,
            'columns'     => $columns,
        ]);
        $this->assertEquals(201, $createResponse['status'],
            'Definition creation should return 201. Got: ' . ($createResponse['raw'] ?? ''));
        $defId = $createResponse['body']['data']['id'] ?? null;
        $this->assertNotNull($defId, 'Definition ID must be returned');

        // Read the definition back
        $readResponse = $this->authenticatedRequest('GET', "/api/v1/reports/definitions/{$defId}", $this->analystSession);
        $this->assertEquals(200, $readResponse['status'],
            'Reading definition should return 200. Got: ' . ($readResponse['raw'] ?? ''));

        $data = $readResponse['body']['data'] ?? [];

        // Assert raw JSON columns exist
        $this->assertArrayHasKey('dimensions_json', $data, 'Response should include dimensions_json');
        $this->assertArrayHasKey('filters_json', $data, 'Response should include filters_json');
        $this->assertArrayHasKey('columns_json', $data, 'Response should include columns_json');

        // Assert decoded frontend-friendly keys exist
        $this->assertArrayHasKey('dimensions', $data, 'Response should include decoded dimensions');
        $this->assertArrayHasKey('filters', $data, 'Response should include decoded filters');
        $this->assertArrayHasKey('columns', $data, 'Response should include decoded columns');

        // Assert decoded values match what was sent
        $this->assertEquals($dimensions['type'], $data['dimensions']['type'] ?? null,
            'Decoded dimensions should match what was stored');
        $this->assertEquals($filters['date_from'], $data['filters']['date_from'] ?? null,
            'Decoded filters should match what was stored');
        $this->assertContains('site_id', $data['columns'],
            'Decoded columns should include site_id');
    }
}
