<?php
/**
 * FOODIIE — session authentication for the admin panel.
 *
 * - password_hash() / password_verify()
 * - session_regenerate_id() on login (fixation protection)
 * - HttpOnly + SameSite=Lax cookies, Secure when HTTPS
 * - 30-minute idle timeout
 * - File-based login rate limiting: 5 failures -> 15-minute lockout per IP
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

const FOODIIE_SESSION_TIMEOUT = 1800; // 30 minutes idle
const FOODIIE_MAX_LOGIN_ATTEMPTS = 5;
const FOODIIE_LOCKOUT_SECONDS = 900; // 15 minutes

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('FOODIIEADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Currently logged-in user row, or null. Enforces idle timeout. */
function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $last = (int) ($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > FOODIIE_SESSION_TIMEOUT) {
        logout();
        return null;
    }
    $_SESSION['last_activity'] = time();
    $stmt = db()->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        logout();
        return null;
    }
    return $user;
}

/** Redirect to login when not authenticated. Call at top of every admin page. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $here = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: login.php' . ($here !== '' ? '?next=' . urlencode($here) : ''));
        exit;
    }
    return $user;
}

/** Attempt login. Returns [ok(bool), message]. */
function attempt_login(string $email, string $password): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (login_locked_out($ip)) {
        return [false, 'Too many failed attempts. Try again in 15 minutes.'];
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :e');
    $stmt->execute([':e' => strtolower(trim($email))]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        record_login_failure($ip);
        return [false, 'Invalid email or password.'];
    }
    clear_login_failures($ip);
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    return [true, ''];
}

function logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---- rate limiting (file based, per IP) ----

function login_attempts_file(): string
{
    return storage_path('logs/login_attempts.json');
}

function read_login_attempts(): array
{
    $f = login_attempts_file();
    if (!is_readable($f)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($f), true);
    return is_array($data) ? $data : [];
}

function write_login_attempts(array $data): void
{
    $f = login_attempts_file();
    $dir = dirname($f);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    // Prune old entries.
    $cutoff = time() - FOODIIE_LOCKOUT_SECONDS - 60;
    foreach ($data as $ip => $info) {
        if (($info['last'] ?? 0) < $cutoff) {
            unset($data[$ip]);
        }
    }
    file_put_contents($f, json_encode($data), LOCK_EX);
}

function login_locked_out(string $ip): bool
{
    $data = read_login_attempts();
    $info = $data[$ip] ?? null;
    if (!$info || (int) ($info['count'] ?? 0) < FOODIIE_MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    return (time() - (int) ($info['last'] ?? 0)) < FOODIIE_LOCKOUT_SECONDS;
}

function record_login_failure(string $ip): void
{
    $data = read_login_attempts();
    $info = $data[$ip] ?? ['count' => 0, 'last' => 0];
    $info['count'] = (int) $info['count'] + 1;
    $info['last'] = time();
    $data[$ip] = $info;
    write_login_attempts($data);
}

function clear_login_failures(string $ip): void
{
    $data = read_login_attempts();
    unset($data[$ip]);
    write_login_attempts($data);
}
