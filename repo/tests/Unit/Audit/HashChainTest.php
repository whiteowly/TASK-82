<?php
declare(strict_types=1);

namespace tests\Unit\Audit;

use app\service\audit\AuditHashService;
use tests\TestCase;

class HashChainTest extends TestCase
{
    private AuditHashService $service;

    protected function setUp(): void
    {
        $this->service = new AuditHashService();
    }

    public function testComputeEntryHashProducesConsistentHash(): void
    {
        $data = [
            'actor_id' => 1,
            'event_type' => 'login_success',
            'target_type' => 'user',
            'target_id' => 1,
        ];
        $prevHash = str_repeat('0', 64);

        $hash1 = $this->service->computeEntryHash($data, $prevHash);
        $hash2 = $this->service->computeEntryHash($data, $prevHash);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1));
    }

    public function testDifferentDataProducesDifferentHash(): void
    {
        $prevHash = str_repeat('0', 64);

        $hash1 = $this->service->computeEntryHash(
            ['event_type' => 'login_success'],
            $prevHash
        );
        $hash2 = $this->service->computeEntryHash(
            ['event_type' => 'login_failure'],
            $prevHash
        );

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testDifferentPrevHashProducesDifferentHash(): void
    {
        $data = ['event_type' => 'login_success'];

        $hash1 = $this->service->computeEntryHash($data, str_repeat('0', 64));
        $hash2 = $this->service->computeEntryHash($data, str_repeat('a', 64));

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testChainIntegrity(): void
    {
        $entries = [];
        $prevHash = str_repeat('0', 64);

        for ($i = 0; $i < 5; $i++) {
            $data = ['event_type' => 'test_event', 'sequence' => $i];
            $entryHash = $this->service->computeEntryHash($data, $prevHash);
            $entries[] = [
                'data' => $data,
                'prev_hash' => $prevHash,
                'entry_hash' => $entryHash,
            ];
            $prevHash = $entryHash;
        }

        // Verify each entry's hash
        foreach ($entries as $entry) {
            $recomputed = $this->service->computeEntryHash($entry['data'], $entry['prev_hash']);
            $this->assertEquals($entry['entry_hash'], $recomputed);
        }

        // Verify chain linkage
        for ($i = 1; $i < count($entries); $i++) {
            $this->assertEquals($entries[$i]['prev_hash'], $entries[$i - 1]['entry_hash']);
        }
    }
}
