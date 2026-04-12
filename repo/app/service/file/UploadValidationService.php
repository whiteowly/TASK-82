<?php
declare(strict_types=1);

namespace app\service\file;

use think\File;
use think\exception\ValidateException;

class UploadValidationService
{
    /**
     * Allowed MIME types for image uploads.
     */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
    ];

    /**
     * Allowed file extensions for image uploads.
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
    ];

    /**
     * Maximum file size in bytes (5 MB).
     */
    private const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Validate an uploaded image file.
     *
     * Checks MIME type whitelist, extension whitelist, and maximum file size.
     *
     * @param File $file The uploaded file instance.
     * @return void
     * @throws ValidateException If any validation check fails.
     */
    public function validateImage(File $file): void
    {
        $mime = $file->getMime();
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new ValidateException(
                "Invalid MIME type: {$mime}. Allowed: " . implode(', ', self::ALLOWED_MIMES)
            );
        }

        $extension = strtolower($file->extension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new ValidateException(
                "Invalid file extension: {$extension}. Allowed: " . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        $size = $file->getSize();
        if ($size > self::MAX_SIZE) {
            throw new ValidateException(
                "File size ({$size} bytes) exceeds the maximum allowed size of " . self::MAX_SIZE . ' bytes.'
            );
        }
    }
}
