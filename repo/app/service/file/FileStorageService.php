<?php
declare(strict_types=1);

namespace app\service\file;

class FileStorageService
{
    /**
     * Store an uploaded file with a randomized filename.
     *
     * @param string $tmpPath      Temporary file path from the upload.
     * @param string $originalName Original filename (used only for extension extraction).
     * @param string $mimeType     MIME type of the file.
     * @return array Metadata: stored_path, original_name, mime_type, size.
     */
    public function store(string $tmpPath, string $originalName, string $mimeType): array
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $randomName = bin2hex(random_bytes(16)) . ($extension ? '.' . $extension : '');

        $datePath  = date('Y/m/d');
        $storagDir = '/app/storage/uploads/' . $datePath;

        if (!is_dir($storagDir)) {
            mkdir($storagDir, 0777, true);
        }
        // Ensure write permissions on the entire path chain (suppress if not owner)
        $base = '/app/storage/uploads';
        foreach (explode('/', trim($datePath, '/')) as $segment) {
            $base .= '/' . $segment;
            if (is_dir($base)) @chmod($base, 0777);
        }

        $storedPath = $storagDir . '/' . $randomName;
        rename($tmpPath, $storedPath);

        return [
            'stored_path'   => $datePath . '/' . $randomName,
            'original_name' => $originalName,
            'mime_type'     => $mimeType,
            'size'          => filesize($storedPath),
        ];
    }

    /**
     * Resolve the full filesystem path for a stored file.
     *
     * @param string $storedPath Relative stored path.
     * @return string Absolute path.
     */
    public function getSecurePath(string $storedPath): string
    {
        return '/app/storage/uploads/' . $storedPath;
    }
}
