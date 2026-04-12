<?php
declare(strict_types=1);

namespace app\service\file;

use think\facade\Db;

class FingerprintService
{
    /**
     * Compute the SHA-256 fingerprint of a file.
     *
     * @param string $filePath Absolute path to the file.
     * @return string Hex-encoded SHA-256 hash.
     */
    public function compute(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }

    /**
     * Check whether a file with the given hash already exists in storage.
     *
     * @param string $hash SHA-256 hash to look up.
     * @return array|null Existing file record or null if no duplicate found.
     */
    public function isDuplicate(string $hash): ?array
    {
        $record = Db::table('recipe_images')
            ->where('sha256_hash', $hash)
            ->find();

        return $record ?: null;
    }
}
