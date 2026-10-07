<?php
/**
 * FOODIIE — small shared helpers: slugs, excerpts, dates, uploads names.
 */

require_once __DIR__ . '/config.php';

/** URL-safe slug. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    // Transliterate accented chars.
    $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'item' : substr($text, 0, 120);
}

/** Unique slug for a table: appends -2, -3 ... when taken. */
function unique_slug(PDO $pdo, string $table, string $base, ?int $ignore_id = null): string
{
    $slug = slugify($base);
    $try = $slug;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = :s";
        $params = [':s' => $try];
        if ($ignore_id !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $ignore_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $try;
        }
        $try = $slug . '-' . $i;
        $i++;
    }
}

/** Plain-text excerpt from HTML. */
function excerpt(string $html, int $len = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return mb_substr($text, 0, $len - 1) . '…';
}

/** Estimated reading time in minutes. */
function reading_time(string $html): int
{
    $words = str_word_count(strip_tags($html));
    return max(1, (int) ceil($words / 200));
}

/** Human date, e.g. "29 Sep 2026". */
function fmt_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date('j M Y', $ts) : '';
}

/** ISO date for JSON-LD. */
function iso_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? gmdate('c', $ts) : '';
}

/**
 * Safe upload filename: slugified base + random suffix, single whitelisted ext.
 * Guards against double extensions and path traversal.
 */
function safe_upload_name(string $original, string $ext): string
{
    $base = pathinfo($original, PATHINFO_FILENAME);
    $base = slugify($base);
    if ($base === '' || $base === 'item') {
        $base = 'image';
    }
    return substr($base, 0, 60) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
}

/** Split a comma-separated tags string into a clean array. */
function parse_tags(string $tags): array
{
    $out = [];
    foreach (explode(',', $tags) as $t) {
        $t = trim($t);
        if ($t !== '') {
            $out[] = $t;
        }
    }
    return array_values(array_unique($out));
}

/**
 * PHP 7.4 polyfills for the PHP 8.0 string helpers.
 * Only defined when missing, so PHP 8.x uses its native versions.
 */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
