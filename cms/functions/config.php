<?php
/**
 * FOODIIE — configuration loader.
 *
 * Loads .env (project root) into a static store and exposes the two
 * URL concepts the whole project depends on:
 *
 *   APP_URL   - canonical public origin, e.g. https://foodiie.in
 *               On WAMP in a subfolder: http://localhost/foodiie/public
 *   BASE_PATH - URL path prefix of the *public* site, derived from APP_URL
 *               (or overridden with BASE_PATH in .env).
 *               '' at domain root, '/foodiie/public' in a WAMP subfolder.
 *
 * All public links/assets/form-actions MUST be built with u() so the
 * generated site works from a subfolder. Canonical/OG URLs use abs_url().
 */

define('FOODIIE_ROOT', dirname(__DIR__, 2)); // .../foodiie

final class FoodiieConfig
{
    private static array $env = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        $file = FOODIIE_ROOT . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $val = trim(substr($line, $pos + 1));
                // Strip optional surrounding quotes.
                if (strlen($val) >= 2 && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
                    $val = substr($val, 1, -1);
                }
                // Real environment wins over .env file.
                if (getenv($key) === false && !array_key_exists($key, self::$env)) {
                    self::$env[$key] = $val;
                }
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        self::load();
        $v = getenv($key);
        if ($v !== false) {
            return $v;
        }
        return self::$env[$key] ?? $default;
    }
}

/** env() shortcut */
function env(string $key, $default = null)
{
    return FoodiieConfig::get($key, $default);
}

/** Canonical public origin, no trailing slash. */
function app_url(): string
{
    return rtrim((string) env('APP_URL', 'https://foodiie.in'), '/');
}

/**
 * URL path prefix of the public site ('' at domain root,
 * '/foodiie/public' on WAMP subfolder installs).
 */
function base_path(): string
{
    $override = trim((string) env('BASE_PATH', ''));
    if ($override !== '') {
        return $override === '/' ? '' : rtrim($override, '/');
    }
    $path = parse_url(app_url(), PHP_URL_PATH);
    if (!is_string($path) || $path === '' || $path === '/') {
        return '';
    }
    return rtrim($path, '/');
}

/**
 * Public URL for a path inside the public site.
 * u('assets/css/style.css') -> '/assets/css/style.css'
 *   or '/foodiie/public/assets/css/style.css' on WAMP subfolder.
 */
function u(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $base = base_path();
    return ($base === '' ? '' : $base) . $path;
}

/** Absolute canonical URL for a public path. */
function abs_url(string $path): string
{
    return app_url() . '/' . ltrim($path, '/');
}

/** HTML-escape. */
function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** True when the current request is over HTTPS. */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    return false;
}

/** Storage paths (absolute, filesystem). */
function storage_path(string $sub = ''): string
{
    $p = FOODIIE_ROOT . '/storage';
    return $sub === '' ? $p : $p . '/' . ltrim($sub, '/');
}
