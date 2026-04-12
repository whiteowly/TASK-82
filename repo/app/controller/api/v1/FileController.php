<?php
declare(strict_types=1);

namespace app\controller\api\v1;

use app\controller\BaseController;
use app\service\file\FingerprintService;
use app\service\file\FileStorageService;
use think\facade\Db;
use think\Response;
use think\file\UploadedFile;

class FileController extends BaseController
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const UPLOAD_ROLES = ['content_editor', 'reviewer', 'administrator'];

    public function uploadImage(
        FingerprintService $fingerprintService,
        FileStorageService $storageService
    ): Response {
        // Role gate
        if (empty(array_intersect($this->request->roles, self::UPLOAD_ROLES))) {
            return $this->error('FORBIDDEN_ROLE', 'Only editors, reviewers, or administrators can upload images.', [], 403);
        }

        $file = $this->request->file('file') ?? $this->request->file('image');
        if (!$file) {
            return $this->error('FILE_MISSING', 'No image file was uploaded.', [], 422);
        }

        // version_id required — every upload must link to a recipe version
        $versionId = (int)($this->request->post('version_id') ?: $this->request->get('version_id', 0));
        if (!$versionId) {
            return $this->error('VALIDATION_FAILED', 'version_id is required for image uploads.', [], 422);
        }

        $version = Db::name('recipe_versions')->where('id', $versionId)->find();
        if (!$version) {
            return $this->error('NOT_FOUND', 'Recipe version not found.', [], 404);
        }

        // Site scope enforcement: resolve version → recipe → site_id
        $recipe = Db::name('recipes')->where('id', $version['recipe_id'])->find();
        if (!$recipe || !$this->canAccessSite((int)$recipe['site_id'])) {
            return $this->error('FORBIDDEN_SITE_SCOPE', 'You do not have access to this site.', [], 403);
        }

        // Validate extension
        $extension = strtolower($file->extension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return $this->error('FILE_TYPE_NOT_ALLOWED', 'Only JPG and PNG images are accepted.', [], 422);
        }

        // Validate MIME
        $mimeType = $file->getMime();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->error('FILE_TYPE_NOT_ALLOWED', 'Only JPG and PNG images are accepted.', [], 422);
        }

        // Validate size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->error('FILE_TOO_LARGE', 'Image must be smaller than 5 MB.', [], 422);
        }

        $fileSize = $file->getSize();
        $sha256 = $fingerprintService->compute($file->getRealPath());

        // Duplicate detection on persisted recipe_images
        $existing = $fingerprintService->isDuplicate($sha256);
        if ($existing) {
            return $this->success([
                'sha256'    => $sha256,
                'duplicate' => true,
                'existing'  => $existing,
            ], 200);
        }

        // Store file
        $stored = $storageService->store($file->getRealPath(), $file->getOriginalName(), $mimeType);
        $path = is_array($stored) ? ($stored['stored_path'] ?? '') : (string)$stored;

        // Persist durable recipe_images record
        $maxSort = (int)(Db::name('recipe_images')->where('version_id', $versionId)->max('sort_order') ?: 0);
        $imageId = Db::name('recipe_images')->insertGetId([
            'version_id'    => $versionId,
            'file_path'     => $path,
            'original_name' => $file->getOriginalName(),
            'mime_type'     => $mimeType,
            'file_size'     => $fileSize,
            'sha256_hash'   => $sha256,
            'sort_order'    => $maxSort + 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return $this->success([
            'path'      => $path,
            'sha256'    => $sha256,
            'mime_type' => $mimeType,
            'size'      => $fileSize,
            'duplicate' => false,
            'image_id'  => $imageId,
        ], 201);
    }
}
