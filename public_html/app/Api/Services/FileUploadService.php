<?php
namespace Api\Services;

use RuntimeException;

/**
 * Saves uploaded files safely.
 *  - checks it is a real HTTP upload
 *  - checks size
 *  - checks the extension AND the real file content type (so "shell.php" renamed
 *    to "shell.jpg" is rejected)
 *  - stores it under a random file name
 */
final class FileUploadService
{
    /** extension => allowed real MIME types */
    private const MIME_TYPES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
    ];

    /**
     * @param array  $file         one entry in $_FILES format (name, tmp_name, error, size)
     * @param string $absoluteDir  folder on disk to save into
     * @param string $publicDir    the same folder as a relative path stored in the DB, e.g. "uploads/applications/5"
     * @param string $prefix       file name prefix, e.g. "valid_id"
     * @param array  $allowedExt   e.g. ['pdf','jpg']
     * @return string|null         relative path, or null if no file was sent
     * @throws RuntimeException    when the file is invalid
     */
    public function save(array $file, string $absoluteDir, string $publicDir, string $prefix, array $allowedExt, int $maxMb): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload of \"$prefix\" failed.");
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            throw new RuntimeException("Upload of \"$prefix\" is not a valid upload.");
        }
        if (($file['size'] ?? 0) > $maxMb * 1024 * 1024) {
            throw new RuntimeException("File \"$prefix\" exceeds {$maxMb}MB limit.");
        }

        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true) || !isset(self::MIME_TYPES[$ext])) {
            throw new RuntimeException("Invalid file format for \"$prefix\". Accepted: " . implode(', ', $allowedExt));
        }

        if (class_exists('finfo')) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!in_array($mime, self::MIME_TYPES[$ext], true)) {
                throw new RuntimeException("File \"$prefix\" content does not match its .$ext extension.");
            }
        }

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Could not create upload folder.');
        }

        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $filename)) {
            throw new RuntimeException("Could not save \"$prefix\".");
        }

        return rtrim($publicDir, '/') . '/' . $filename;
    }

    /**
     * Flattens $_FILES into a simple list, supporting both
     * `photo=<file>` and `photos[]=<file1>&photos[]=<file2>` styles.
     */
    public static function flatten(array $files): array
    {
        $list = [];
        foreach ($files as $info) {
            if (is_array($info['name'] ?? null)) {
                foreach ($info['name'] as $i => $name) {
                    $list[] = [
                        'name'     => $name,
                        'tmp_name' => $info['tmp_name'][$i] ?? '',
                        'error'    => $info['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size'     => $info['size'][$i] ?? 0,
                    ];
                }
            } else {
                $list[] = $info;
            }
        }
        return $list;
    }
}
