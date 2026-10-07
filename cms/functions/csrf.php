<?php
/**
 * FOODIIE — CSRF protection for admin + public forms.
 */

require_once __DIR__ . '/config.php';

function csrf_token(): string
{
    start_session_if_needed();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function start_session_if_needed(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Lightweight session for public forms; admin uses auth.php's hardened session.
        session_name('FOODIIEADMIN');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'domain' => '',
            'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/** Hidden input field HTML. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc(csrf_token()) . '">';
}

/** Verify the token from POST. Returns bool. */
function verify_csrf(): bool
{
    start_session_if_needed();
    $sent = $_POST['csrf_token'] ?? '';
    $have = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || $sent === '' || $have === '') {
        return false;
    }
    return hash_equals((string) $have, $sent);
}

/** Die with 403 when CSRF is invalid. */
function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid or missing CSRF token.';
        exit;
    }
}
