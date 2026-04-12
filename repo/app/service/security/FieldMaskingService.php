<?php
declare(strict_types=1);

namespace app\service\security;

class FieldMaskingService
{
    /**
     * Fields that require masking and which roles are exempt.
     * Roles listed here can see the unmasked value.
     */
    private const MASKING_POLICY = [
        'tax_id'           => ['administrator'],
        'tax_id_encrypted' => ['administrator'],
        'phone'            => ['administrator', 'auditor'],
        'password_hash'    => [],  // never shown to anyone
        'email'            => ['administrator', 'auditor'],
        'national_id'      => ['administrator'],
        'bank_account'     => ['administrator'],
    ];

    /**
     * Mask a value, showing only the last 4 characters.
     */
    public function mask(string $value, string $policy = 'default'): string
    {
        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - 4) . mb_substr($value, -4);
    }

    /**
     * Determine whether a field should be masked for the given roles.
     */
    public function shouldMask(string $field, array $userRoles): bool
    {
        if (!isset(self::MASKING_POLICY[$field])) {
            return false;
        }

        $exemptRoles = self::MASKING_POLICY[$field];
        if (empty($exemptRoles)) {
            return true; // always mask (e.g. password_hash)
        }

        return empty(array_intersect($userRoles, $exemptRoles));
    }

    /**
     * Apply masking to an entire record's maskable fields.
     */
    public function applyMaskingToRecord(array $record, array $maskableFields, array $userRoles): array
    {
        foreach ($maskableFields as $field) {
            if (isset($record[$field]) && is_string($record[$field]) && $this->shouldMask($field, $userRoles)) {
                $record[$field] = $this->mask($record[$field]);
            }
        }
        return $record;
    }
}
