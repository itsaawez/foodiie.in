<?php
/**
 * FOODIIE — media upload handling.
 *
 * Strict allowlist: jpg/jpeg/png/webp/avif/gif only.
 * Rejects: SVG, PHP/double extensions, MIME mismatches, non-images.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

const FOODIIE_ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'];
const FOODIIE_ALLOWED_MIMES = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png', 'webp' => 'image/webp',
    'avif' => 'image/avif', 'gif' => 'image/gif',
];
const FOODIIE_MAX_UPLOAD_BYTES = 8 * 1024 * 1024; // 8 MB

/** Absolute uploads dir (storage/uploads/YYYY/MM), honoring UPLOAD_PATH. */
function uploads_dir(): string
{
    $root = trim((string) env('UPLOAD_PATH', ''));
    if ($root === '') {
        $root = storage_path('uploads');
    }
    $dir = rtrim($root, '/') . '/' . date('Y') . '/' . date('m');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Validate + store an uploaded file.
 * Returns [ok, message, row|null]. $row is the media DB row.
 */
function handle_upload(array $file, string $alt = ''): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed (error code ' . ($file['error'] ?? '?') . ').', null];
    }
    if (($file['size'] ?? 0) > FOODIIE_MAX_UPLOAD_BYTES) {
        return [false, 'File is too large (max 8 MB).', null];
    }

    $original = (string) ($file['name'] ?? '');
    // Reject path traversal attempts in the client filename.
    if (strpos($original, '..') !== false || strpos($original, '/') !== false || strpos($original, '\\') !== false) {
        return [false, 'Invalid filename.', null];
    }
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, FOODIIE_ALLOWED_EXTS, true)) {
        return [false, 'File type not allowed. Use JPG, PNG, WebP, AVIF or GIF.', null];
    }
    // Reject double extensions like photo.jpg.php
    if (preg_match('/\.(php|phtml|phar|svg|html|js)\b/i', pathinfo($original, PATHINFO_FILENAME))) {
        return [false, 'File type not allowed.', null];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return [false, 'Invalid upload.', null];
    }

    // MIME check via finfo (never trust the client-supplied type).
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    if ($mime !== FOODIIE_ALLOWED_MIMES[$ext]) {
        return [false, 'File content does not match its extension.', null];
    }

    // Must be a real image.
    $info = @getimagesize($tmp);
    if ($info === false) {
        return [false, 'File is not a valid image.', null];
    }

    $name = safe_upload_name($original, $ext);
    $dest = uploads_dir() . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        return [false, 'Could not save the uploaded file.', null];
    }
    chmod($dest, 0644);

    // Optional: downscale very large images with GD to keep the site fast.
    maybe_downscale($dest, $ext, 1920);

    $info = @getimagesize($dest);
    $rel = 'uploads/' . date('Y') . '/' . date('m') . '/' . $name; // relative to storage/uploads

    $stmt = db()->prepare('INSERT INTO media(filename, original_name, mime, size, width, height, alt, created_at)
        VALUES(:f, :o, :m, :s, :w, :h, :a, :c)');
    $stmt->execute([
        ':f' => $rel,
        ':o' => substr($original, 0, 200),
        ':m' => $mime,
        ':s' => filesize($dest),
        ':w' => $info[0] ?? 0,
        ':h' => $info[1] ?? 0,
        ':a' => substr($alt, 0, 200),
        ':c' => now_utc(),
    ]);
    $id = (int) db()->lastInsertId();
    $row = db()->query('SELECT * FROM media WHERE id = ' . $id)->fetch();
    return [true, 'Uploaded.', $row];
}

/** Downscale images wider than $max_w using GD (best effort). */
function maybe_downscale(string $path, string $ext, int $max_w): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }
    $info = @getimagesize($path);
    if (!$info || $info[0] <= $max_w) {
        return;
    }
    $src = false;
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = @imagecreatefromjpeg($path);
            break;
        case 'png':
            $src = @imagecreatefrompng($path);
            break;
        case 'webp':
            $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
            break;
        case 'avif':
            $src = function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false;
            break;
        case 'gif':
            $src = @imagecreatefromgif($path);
            break;
    }
    if (!$src) {
        return;
    }
    $ratio = $max_w / $info[0];
    $w = $max_w;
    $h = (int) round($info[1] * $ratio);
    $dst = imagecreatetruecolor($w, $h);
    if (in_array($ext, ['png', 'webp', 'avif', 'gif'], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($dst, $path, 82);
            break;
        case 'png':
            imagepng($dst, $path, 6);
            break;
        case 'webp':
            if (function_exists('imagewebp')) {
                imagewebp($dst, $path, 82);
            }
            break;
        case 'avif':
            if (function_exists('imageavif')) {
                imageavif($dst, $path, 60);
            }
            break;
        case 'gif':
            imagegif($dst, $path);
            break;
    }
    imagedestroy($src);
    imagedestroy($dst);
}

/** Delete a media row + its file. */
function delete_media(int $id): bool
{
    $stmt = db()->prepare('SELECT * FROM media WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    $path = storage_path('uploads/' . $row['filename']);
    // Guard: resolved path must stay inside storage/uploads.
    $real = realpath($path);
    $base = realpath(storage_path('uploads'));
    if ($real && $base && strpos($real, $base) === 0 && is_file($real)) {
        unlink($real);
    }
    db()->prepare('DELETE FROM media WHERE id = :id')->execute([':id' => $id]);
    return true;
}
