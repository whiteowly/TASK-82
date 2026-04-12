<?php
declare(strict_types=1);

namespace tests\Feature\Security;

use app\service\security\FieldMaskingService;
use tests\TestCase;

class FieldMaskingTest extends TestCase
{
    private FieldMaskingService $service;

    protected function setUp(): void
    {
        $this->service = new FieldMaskingService();
    }

    public function testMaskHidesAllButLastFourChars(): void
    {
        $masked = $this->service->mask('123-45-6789');
        $this->assertStringEndsWith('6789', $masked);
        $this->assertStringStartsWith('***', $masked);
    }

    public function testShortStringIsMaskedCompletely(): void
    {
        $masked = $this->service->mask('AB');
        $this->assertEquals('**', $masked);
    }

    public function testEditorSeesMaskedTaxIdAndPhone(): void
    {
        $record = ['name' => 'John', 'tax_id' => '123-45-6789', 'phone' => '555-1234'];
        $masked = $this->service->applyMaskingToRecord($record, ['tax_id', 'phone'], ['content_editor']);

        $this->assertEquals('John', $masked['name']);
        $this->assertNotEquals('123-45-6789', $masked['tax_id']);
        $this->assertNotEquals('555-1234', $masked['phone']);
    }

    public function testAdministratorSeesUnmaskedTaxIdAndPhone(): void
    {
        $record = ['tax_id' => '123-45-6789', 'phone' => '555-1234'];
        $masked = $this->service->applyMaskingToRecord($record, ['tax_id', 'phone'], ['administrator']);

        $this->assertEquals('123-45-6789', $masked['tax_id']);
        $this->assertEquals('555-1234', $masked['phone']);
    }

    public function testAuditorSeesUnmaskedPhoneButMaskedTaxId(): void
    {
        $record = ['tax_id' => '123-45-6789', 'phone' => '555-1234'];
        $masked = $this->service->applyMaskingToRecord($record, ['tax_id', 'phone'], ['auditor']);

        $this->assertNotEquals('123-45-6789', $masked['tax_id']);
        $this->assertEquals('555-1234', $masked['phone']);
    }

    public function testPasswordHashAlwaysMasked(): void
    {
        $this->assertTrue($this->service->shouldMask('password_hash', ['administrator']));
        $this->assertTrue($this->service->shouldMask('password_hash', ['auditor']));
    }

    public function testUnknownFieldIsNotMasked(): void
    {
        $this->assertFalse($this->service->shouldMask('name', ['content_editor']));
        $this->assertFalse($this->service->shouldMask('address', ['finance_clerk']));
    }

    // --- Issue 1: email, national_id, bank_account masking ---

    public function testEmailMaskedForAnalyst(): void
    {
        $this->assertTrue($this->service->shouldMask('email', ['operations_analyst']));
    }

    public function testEmailUnmaskedForAdmin(): void
    {
        $this->assertFalse($this->service->shouldMask('email', ['administrator']));
    }

    public function testEmailUnmaskedForAuditor(): void
    {
        $this->assertFalse($this->service->shouldMask('email', ['auditor']));
    }

    public function testNationalIdMaskedForAnalyst(): void
    {
        $this->assertTrue($this->service->shouldMask('national_id', ['operations_analyst']));
    }

    public function testNationalIdUnmaskedForAdmin(): void
    {
        $this->assertFalse($this->service->shouldMask('national_id', ['administrator']));
    }

    public function testNationalIdMaskedForAuditor(): void
    {
        $this->assertTrue($this->service->shouldMask('national_id', ['auditor']));
    }

    public function testBankAccountMaskedForEditor(): void
    {
        $this->assertTrue($this->service->shouldMask('bank_account', ['content_editor']));
    }

    public function testBankAccountUnmaskedForAdmin(): void
    {
        $this->assertFalse($this->service->shouldMask('bank_account', ['administrator']));
    }

    public function testBankAccountMaskedForAuditor(): void
    {
        $this->assertTrue($this->service->shouldMask('bank_account', ['auditor']));
    }

    public function testApplyMaskingCoversAllSensitiveFields(): void
    {
        $record = [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'national_id' => 'ID-12345678',
            'bank_account' => 'ACCT-99887766',
            'tax_id' => '123-45-6789',
            'phone' => '555-9876',
        ];
        $allSensitive = ['email', 'national_id', 'bank_account', 'tax_id', 'phone'];

        // Analyst: everything masked
        $masked = $this->service->applyMaskingToRecord($record, $allSensitive, ['operations_analyst']);
        $this->assertNotEquals('jane@example.com', $masked['email']);
        $this->assertNotEquals('ID-12345678', $masked['national_id']);
        $this->assertNotEquals('ACCT-99887766', $masked['bank_account']);
        $this->assertNotEquals('123-45-6789', $masked['tax_id']);
        $this->assertNotEquals('555-9876', $masked['phone']);

        // Admin: all unmasked
        $unmasked = $this->service->applyMaskingToRecord($record, $allSensitive, ['administrator']);
        $this->assertEquals('jane@example.com', $unmasked['email']);
        $this->assertEquals('ID-12345678', $unmasked['national_id']);
        $this->assertEquals('ACCT-99887766', $unmasked['bank_account']);
        $this->assertEquals('123-45-6789', $unmasked['tax_id']);
        $this->assertEquals('555-9876', $unmasked['phone']);
    }
}
