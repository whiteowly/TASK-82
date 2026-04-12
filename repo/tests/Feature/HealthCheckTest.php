<?php
declare(strict_types=1);

namespace tests\Feature;

use tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/health');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('data', $response['body']);
        $this->assertEquals('ok', $response['body']['data']['status'] ?? '');
    }

    public function testHealthEndpointIncludesTimestamp(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/health');

        $this->assertArrayHasKey('data', $response['body']);
        $this->assertArrayHasKey('timestamp', $response['body']['data']);
    }

    public function testHealthEndpointIncludesRequestId(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/health');

        $this->assertArrayHasKey('meta', $response['body']);
        $this->assertArrayHasKey('request_id', $response['body']['meta']);
        $this->assertStringStartsWith('req-', $response['body']['meta']['request_id']);
    }
}
