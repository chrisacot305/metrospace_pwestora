<?php
namespace App\Core;

use RuntimeException;

/**
 * Upload — validates and saves uploaded files.
 *  - must be a real HTTP upload
 *  - size limit
 *  - extension whitelist AND real content check (finfo), so "shell.php" renamed
 *    to "photo.jpg" is rejected
 *  - random file names
 */
final class Upload
{
    /** Files saved during this request (so they can be removed if the database step fails). */
    private static array $savedThisRequest = [];

    private const MIME = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
    ];

    /**
     * @param array  $file      one $_FILES entry (name, tmp_name, error, size)
     * @param string $subDir    folder inside uploads/, e.g. "properties/12"
     * @param string $prefix    file name prefix, e.g. "title_doc"
     * @return string           stored path relative to public_html, e.g. "uploads/properties/12/title_doc_ab12cd34.pdf"
     */
    public static function save(array $file, string $subDir, string $prefix, array $allowedExt, int $maxMb,
                                string $typeError, string $sizeError, string $failError = 'Upload failed. Please try again.'): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($failError);
        }
        if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) {
            throw new RuntimeException($sizeError);
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true) || !isset(self::MIME[$ext])) {
            throw new RuntimeException($typeError);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException($failError);
        }
        if (class_exists('finfo')) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (!in_array($mime, self::MIME[$ext], true)) {
                throw new RuntimeException($typeError);
            }
        }

        $subDir = trim(str_replace(['..', '\\'], '', $subDir), '/');
        $dir = UPLOAD_DIR . '/' . $subDir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not save the uploaded file. Please try again.');
        }
        $filename = $prefix . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($tmp, "$dir/$filename")) {
            throw new RuntimeException('Could not save the uploaded file. Please try again.');
        }
        self::$savedThisRequest[] = "$dir/$filename";
        return "uploads/{$subDir}/{$filename}";
    }

    /** Deletes every file saved in this request — call it when the database transaction is rolled back. */
    public static function discardSaved(): void
    {
        foreach (self::$savedThisRequest as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        self::$savedThisRequest = [];
    }
}
