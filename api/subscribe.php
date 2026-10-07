<?php
/**
 * FOODIIE api/subscribe.php — newsletter signup endpoint.
 * Appends email,date,source to storage/newsletter.csv.
 * Protections: CSRF token, honeypot, email validation, per-IP rate limit.
 */
require_once __DIR__ . '/../cms/functions/config.php';
require_once __DIR__ . '/../cms/functions/csrf.php';

header('Content-Type: application/json; charset=utf-8');

function form_rate_limited(string $ip, string $scope, int $max, int $window): bool
{
    $file = storage_path('logs/form_attempts.json');
    $data = is_readable($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $now = time();
    $key = $scope . '|' . $ip;
    $hits = array_filter($data[$key] ?? [], fn($t) => $now - $t < $window);
    if (count($hits) >= $max) {
        return true;
    }
    $hits[] = $now;
    $data[$key] = array_values($hits);
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    file_put_contents($file, json_encode($data), LOCK_EX);
    return false;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}
if (!verify_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Security check failed. Please reload the page and try again.']);
    exit;
}
// Honeypot: bots fill this hidden field.
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]); // pretend success
    exit;
}
if (form_rate_limited($ip, 'subscribe', 10, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many attempts. Please try again later.']);
    exit;
}
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}
$source = substr(trim((string) ($_POST['source'] ?? 'website')), 0, 50) ?: 'website';

$csv = storage_path('newsletter.csv');
$new = !is_file($csv);
$fp = fopen($csv, 'ab');
if (!$fp) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not save. Please try again later.']);
    exit;
}
if ($new) {
    fputcsv($fp, ['email', 'date', 'source']);
}
// Avoid duplicates.
$exists = false;
foreach (file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $row = str_getcsv($line);
    if (isset($row[0]) && strtolower(trim($row[0])) === $email) {
        $exists = true;
        break;
    }
}
if (!$exists) {
    fputcsv($fp, [$email, gmdate('Y-m-d H:i:s'), $source]);
}
fclose($fp);
echo json_encode(['ok' => true]);
