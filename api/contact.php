<?php
/**
 * FOODIIE api/contact.php — contact form endpoint.
 * Stores messages in storage/contact.csv and optionally emails
 * the notification address from settings when mail is configured.
 * Protections: CSRF token, honeypot, validation, per-IP rate limit.
 */
require_once __DIR__ . '/../cms/functions/config.php';
require_once __DIR__ . '/../cms/functions/csrf.php';
require_once __DIR__ . '/../cms/functions/db.php';

header('Content-Type: application/json; charset=utf-8');

function contact_rate_limited(string $ip): bool
{
    $file = storage_path('logs/form_attempts.json');
    $data = is_readable($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $now = time();
    $key = 'contact|' . $ip;
    $hits = array_filter($data[$key] ?? [], fn($t) => $now - $t < 3600);
    if (count($hits) >= 5) {
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
if (!empty($_POST['website'])) { // honeypot
    echo json_encode(['ok' => true]);
    exit;
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (contact_rate_limited($ip)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many messages. Please try again later.']);
    exit;
}

$name = substr(preg_replace('/[\r\n]+/', ' ', trim((string) ($_POST['name'] ?? ''))), 0, 100);
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$subject = substr(preg_replace('/[\r\n]+/', ' ', trim((string) ($_POST['subject'] ?? ''))), 0, 150);
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $subject === '' || mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
    http_response_code(422);
    echo json_encode(['error' => 'Please fill all fields (message: 10–5000 characters).']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

$csv = storage_path('contact.csv');
$new = !is_file($csv);
if (!is_dir(dirname($csv))) {
    mkdir(dirname($csv), 0755, true);
}
$fp = fopen($csv, 'ab');
if ($fp) {
    if ($new) {
        fputcsv($fp, ['date', 'ip', 'name', 'email', 'subject', 'message']);
    }
    fputcsv($fp, [gmdate('Y-m-d H:i:s'), $ip, $name, $email, $subject, $message]);
    fclose($fp);
}

// Optional email notification.
$notify = setting('contact_email', '');
if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    $headers = 'From: ' . env('MAIL_FROM', 'noreply@foodiie.in') . "\r\n" .
        'Reply-To: ' . $email . "\r\n" .
        'Content-Type: text/plain; charset=utf-8';
    @mail($notify, '[Foodiie Contact] ' . $subject,
        "Name: {$name}\nEmail: {$email}\n\n{$message}", $headers);
}

echo json_encode(['ok' => true]);
