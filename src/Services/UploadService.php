<?php

namespace FurEver\Services;

use FurEver\Core\Env;
use InvalidArgumentException;
use RuntimeException;

final class UploadService
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public function __construct(private string $publicSubdir = 'animals') {}

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     * @return string|null Web path relative to /public (e.g. uploads/animals/abcd.jpg), or null if no file uploaded.
     */
    public function storeImage(array $file): ?string
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload error code ' . $file['error']);
        }

        $maxBytes = Env::int('UPLOAD_MAX_BYTES', 2_097_152);
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new InvalidArgumentException('File is too large (max ' . $maxBytes . ' bytes).');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new InvalidArgumentException('Unsupported image type: ' . $mime);
        }

        $ext = self::ALLOWED_MIMES[$mime];
        $name = bin2hex(random_bytes(12)) . '.' . $ext;

        $absDir = $this->absoluteDir();
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new RuntimeException('Could not create upload directory: ' . $absDir);
        }

        $absPath = $absDir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            // Fallback for tests (non-uploaded files)
            if (!@rename($file['tmp_name'], $absPath)) {
                throw new RuntimeException('Failed to store uploaded file.');
            }
        }

        return 'uploads/' . $this->publicSubdir . '/' . $name;
    }

    public function delete(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $abs = dirname(__DIR__, 2) . '/public/' . ltrim($relativePath, '/');
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private function absoluteDir(): string
    {
        return dirname(__DIR__, 2) . '/public/uploads/' . $this->publicSubdir;
    }
}
