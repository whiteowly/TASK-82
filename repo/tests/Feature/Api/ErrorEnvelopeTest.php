<?php
declare(strict_types=1);

namespace tests\Feature\Api;

use tests\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    public function testNotFoundReturnsErrorEnvelope(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/nonexistent-endpoint');

        $this->assertEquals(404, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertEquals('NOT_FOUND', $response['body']['error']['code']);
        $this->assertArrayHasKey('meta', $response['body']);
        $this->assertArrayHasKey('request_id', $response['body']['meta']);
    }

    public function testErrorEnvelopeHasRequiredFields(): void
    {
        $response = $this->httpRequest('GET', '/api/v1/nonexistent');

        $error = $response['body']['error'] ?? [];
        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('message', $error);
        $this->assertArrayHasKey('details', $error);
        $this->assertIsArray($error['details']);
    }
}
