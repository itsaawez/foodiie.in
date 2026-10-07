<?php
/**
 * FOODIIE — web installer.
 *
 * Run once at http://localhost/foodiie/install/ (or wherever the project
 * lives). It checks the environment, collects the site/admin details,
 * creates the SQLite database, seeds base + demo content, and then locks
 * itself with install.lock so it can never run twice.
 *
 * Standalone: inline CSS, no dependencies beyond cms/functions/*.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak server paths during install

require_once dirname(__DIR__) . '/cms/functions/config.php';
require_once dirname(__DIR__) . '/cms/functions/db.php';
require_once dirname(__DIR__) . '/cms/functions/helpers.php';
require_once dirname(__DIR__) . '/cms/functions/sanitize.php';
require_once dirname(__DIR__) . '/cms/seed.php';

$lock_file = FOODIIE_ROOT . '/install.lock';

/* ------------------------------------------------------------------ */
/* Step 0 — never run twice                                            */
/* ------------------------------------------------------------------ */

if (file_exists($lock_file)) {
    installer_page(
        'Already installed',
        '<div class="card"><h2>FOODIIE is already installed.</h2>'
        . '<p>The installer is locked (<code>install.lock</code> exists). Running it again could wipe your site.</p>'
        . '<p><a class="btn" href="../public/">View site</a> '
        . '<a class="btn btn-ghost" href="../admin/">Admin login</a></p>'
        . '<p class="muted">If you really need to reinstall, delete <code>install.lock</code> and the database file at '
        . '<code>cms/data/foodiie.sqlite</code> — then reload this page.</p></div>'
    );
    exit;
}

/* ------------------------------------------------------------------ */
/* System checks                                                       */
/* ------------------------------------------------------------------ */

function installer_checks(): array
{
    $checks = [];

    $ok = version_compare(PHP_VERSION, '7.4.0', '>=');
    $checks[] = [
        'label' => 'PHP 7.4 or higher',
        'detail' => 'running ' . PHP_VERSION,
        'ok' => $ok,
        'critical' => true,
        'help' => $ok ? '' : 'FOODIIE needs PHP 7.4 or newer. In WAMP: click the WAMP tray icon → PHP → Version → choose a PHP 7.4 (or newer) version → Restart All Services.',
    ];

    $extensions = [
        'pdo_sqlite' => 'php_pdo_sqlite',
        'sqlite3' => 'php_sqlite3',
        'gd' => 'php_gd2',
        'mbstring' => 'php_mbstring',
        'dom' => 'php_dom',
        'zip' => 'php_zip',
    ];
    foreach ($extensions as $ext => $wamp_name) {
        $ok = extension_loaded($ext);
        $checks[] = [
            'label' => 'PHP extension: ' . $ext,
            'detail' => $ok ? 'loaded' : 'missing',
            'ok' => $ok,
            'critical' => ($ext === 'pdo_sqlite'),
            'help' => $ok ? '' : 'In WAMP: click the WAMP tray icon → PHP → PHP extensions → tick '
                . $wamp_name . ' → Restart All Services.',
        ];
    }

    $dirs = ['cms/data', 'storage', 'storage/uploads', 'storage/logs', 'storage/backups', 'public'];
    foreach ($dirs as $dir) {
        $abs = FOODIIE_ROOT . '/' . $dir;
        if (is_dir($abs)) {
            $ok = is_writable($abs);
            $detail = $ok ? 'writable' : 'NOT writable';
            $help = $ok ? '' : 'Make ' . $dir . ' writable by the web server (on Windows/WAMP this is rarely an issue; on Linux: chown/chmod the folder).';
        } else {
            $ok = is_writable(dirname($abs));
            $detail = $ok ? 'will be created' : 'cannot be created';
            $help = $ok ? '' : 'The installer cannot create ' . $dir . ' — check permissions on its parent folder.';
        }
        $checks[] = [
            'label' => 'Folder: ' . $dir,
            'detail' => $detail,
            'ok' => $ok,
            'critical' => false,
            'help' => $help,
        ];
    }

    return $checks;
}

function installer_critical_failed(array $checks): bool
{
    foreach ($checks as $c) {
        if ($c['critical'] && !$c['ok']) {
            return true;
        }
    }
    return false;
}

/** Guess APP_URL from the current request: .../install → .../public. */
function installer_guess_app_url(): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.-]+(?::\d+)?$/', (string) $host)) {
        $host = 'localhost';
    }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($path)) {
        $path = '/';
    }
    $base = preg_replace('#/install(/index\.php)?/?$#', '', $path);
    $base = rtrim((string) $base, '/');
    return $scheme . '://' . $host . $base . '/public';
}

/* ------------------------------------------------------------------ */
/* Handle POST (Step 2)                                                */
/* ------------------------------------------------------------------ */

$errors = [];
$values = [
    'site_name' => 'FOODIIE',
    'admin_name' => '',
    'admin_email' => '',
    'app_url' => installer_guess_app_url(),
    'demo' => '1',
];
$just_installed = false;
$installed_email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Double-submit / race guard: lock file appeared since the form loaded.
    if (file_exists($lock_file)) {
        installer_page('Already installed', '<div class="card"><p>Installation already completed.</p></div>');
        exit;
    }

    $checks = installer_checks();
    if (installer_critical_failed($checks)) {
        $errors[] = 'Critical system checks failed (see the table below). Fix them and try again.';
    }

    $values['site_name'] = trim((string) ($_POST['site_name'] ?? ''));
    $values['admin_name'] = trim((string) ($_POST['admin_name'] ?? ''));
    $values['admin_email'] = strtolower(trim((string) ($_POST['admin_email'] ?? '')));
    $values['app_url'] = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
    $values['demo'] = !empty($_POST['demo']) ? '1' : '';
    $password = (string) ($_POST['admin_password'] ?? '');
    $confirm = (string) ($_POST['admin_password_confirm'] ?? '');

    if ($values['site_name'] === '' || strlen($values['site_name']) > 100) {
        $errors[] = 'Site name is required (max 100 characters).';
    }
    if ($values['admin_name'] === '' || strlen($values['admin_name']) > 100) {
        $errors[] = 'Admin name is required (max 100 characters).';
    }
    if ($values['admin_email'] === '' || !filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($values['admin_email']) > 190) {
        $errors[] = 'A valid admin email address is required.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($values['app_url'] === '' || !filter_var($values['app_url'], FILTER_VALIDATE_URL)
        || !preg_match('#^https?://#i', $values['app_url'])) {
        $errors[] = 'APP_URL must be a valid http(s) URL, e.g. http://localhost/foodiie/public';
    }

    if (!$errors) {
        try {
            // 0. Write the starter .env FIRST, before any db()/env() call,
            //    because FoodiieConfig caches the environment on first load.
            //    (Only if the user has not made one.)
            $env_file = FOODIIE_ROOT . '/.env';
            if (!file_exists($env_file)) {
                file_put_contents(
                    $env_file,
                    '# FOODIIE — generated by the installer on ' . gmdate('Y-m-d') . '. Do not commit to git.' . "\n"
                    . 'APP_URL=' . $values['app_url'] . "\n"
                    . 'APP_ENV=production' . "\n"
                );
            }

            // 1. Create required directories.
            $dirs = ['cms/data', 'storage', 'storage/uploads', 'storage/logs', 'storage/backups', 'public'];
            foreach ($dirs as $dir) {
                $abs = FOODIIE_ROOT . '/' . $dir;
                if (!is_dir($abs) && !mkdir($abs, 0755, true)) {
                    throw new RuntimeException('Could not create directory: ' . $dir);
                }
            }

            // 2. Build the database schema.
            db_install_schema(db());

            // 3. Create the admin user.
            $stmt = db()->prepare("INSERT INTO users(name, email, password_hash, role, created_at)
                VALUES(:name, :email, :hash, 'admin', :created)");
            $stmt->execute([
                ':name' => $values['admin_name'],
                ':email' => $values['admin_email'],
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
                ':created' => now_utc(),
            ]);
            $installed_email = $values['admin_email'];

            // 4. Seed base data (+ demo content if requested).
            foodiie_seed(db(), $values['demo'] === '1', $values['site_name']);

            // 4b. Load the generator (no side effects on include).
            // 6. Generate the static site (.env with APP_URL already exists),
            //    so public/ is live the moment installation finishes.
            //    This runs BEFORE the lock is written: if generation fails,
            //    the installer can simply be re-run (no lock yet).
            require_once dirname(__DIR__) . '/cms/generator/generate.php';
            if (function_exists('generate_site')) {
                generate_site();
            }

            // 7. Lock the installer so it can never run again.
            file_put_contents($lock_file, 'Installed: ' . gmdate('Y-m-d H:i:s') . " UTC\n");

            // 8. Deny all web access to install/ from now on.
            file_put_contents(FOODIIE_ROOT . '/install/.htaccess', "Require all denied\n");

            $just_installed = true;
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

/* ------------------------------------------------------------------ */
/* Render                                                              */
/* ------------------------------------------------------------------ */

if ($just_installed) {
    $demo_note = $values['demo'] === '1'
        ? '<p>Demo content was installed: 10 articles, 5 recipes and 5 videos. You can delete it from the admin panel any time.</p>'
        : '<p>No demo content was installed — your site starts empty and ready for your own content.</p>';
    installer_page(
        'Installation complete',
        '<div class="card success"><h2>FOODIIE is installed.</h2>'
        . $demo_note
        . '<p>Admin account: <strong>' . esc($installed_email) . '</strong></p>'
        . '<p><a class="btn" href="../public/">View site</a> '
        . '<a class="btn btn-ghost" href="../admin/">Admin login</a></p>'
        . '<div class="callout"><strong>Security reminder:</strong> this installer is now locked '
        . '(<code>install.lock</code> written, <code>install/.htaccess</code> denies all access). '
        . 'For maximum safety you may delete the <code>install/</code> folder entirely. '
        . 'Next step: open the admin panel and run the static site generator to publish your pages.</div>'
        . '</div>'
    );
    exit;
}

// GET, or POST with validation errors → show checks + form.
$checks = installer_checks();
$checks_html = '<table class="checks"><tbody>';
foreach ($checks as $c) {
    $cls = $c['ok'] ? 'pass' : 'fail';
    $badge = $c['ok'] ? 'PASS' : 'FAIL';
    $checks_html .= '<tr><td>' . esc($c['label']) . '</td>'
        . '<td class="' . $cls . '"><span class="badge">' . $badge . '</span> '
        . '<span class="muted">' . esc($c['detail']) . '</span></td></tr>';
    if (!$c['ok'] && $c['help'] !== '') {
        $checks_html .= '<tr class="help"><td colspan="2">' . esc($c['help']) . '</td></tr>';
    }
}
$checks_html .= '</tbody></table>';

$errors_html = '';
if ($errors) {
    $errors_html = '<div class="errors"><strong>Please fix the following:</strong><ul>';
    foreach ($errors as $e) {
        $errors_html .= '<li>' . esc($e) . '</li>';
    }
    $errors_html .= '</ul></div>';
}

$disabled = installer_critical_failed($checks) ? ' disabled' : '';
$demo_checked = $values['demo'] === '1' ? ' checked' : '';
$esc_site_name = esc($values['site_name']);
$esc_admin_name = esc($values['admin_name']);
$esc_admin_email = esc($values['admin_email']);
$esc_app_url = esc($values['app_url']);

$form_html = <<<FORM
<div class="card">
<h2>Site setup</h2>
{$errors_html}
<form method="post" action="">
<label>Site name
<input type="text" name="site_name" value="{$esc_site_name}" maxlength="100" required>
</label>
<label>Admin name
<input type="text" name="admin_name" value="{$esc_admin_name}" maxlength="100" required autocomplete="name">
</label>
<label>Admin email
<input type="email" name="admin_email" value="{$esc_admin_email}" maxlength="190" required autocomplete="email">
</label>
<label>Admin password <span class="muted">(min 8 characters)</span>
<input type="password" name="admin_password" minlength="8" required autocomplete="new-password">
</label>
<label>Confirm password
<input type="password" name="admin_password_confirm" minlength="8" required autocomplete="new-password">
</label>
<label>Public site URL (APP_URL)
<input type="url" name="app_url" value="{$esc_app_url}" required>
<span class="hint">The canonical URL of the generated site, no trailing slash. Auto-detected — change it if you use a custom domain.</span>
</label>
<label class="checkbox"><input type="checkbox" name="demo" value="1"{$demo_checked}> Install demo content (10 articles, 5 recipes, 5 videos)</label>
<button class="btn" type="submit"{$disabled}>Install FOODIIE</button>
</form>
</div>
FORM;

installer_page(
    'Install FOODIIE',
    '<div class="card"><h2>System checks</h2>' . $checks_html . '</div>' . $form_html
);
exit;

/* ------------------------------------------------------------------ */

function installer_page(string $title, string $body): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . esc($title) . ' — FOODIIE installer</title>'
        . '<style>'
        . ':root{--accent:#e8590c;--bg:#faf7f2;--card:#fff;--text:#2b2620;--muted:#8a8177;--line:#e9e2d6;--green:#2f9e44;--red:#e03131}'
        . '*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);'
        . 'font:16px/1.6 -apple-system,"Segoe UI",Roboto,Arial,sans-serif}'
        . '.wrap{max-width:780px;margin:0 auto;padding:32px 16px 64px}'
        . 'header h1{font-size:28px;margin:0 0 4px}header p{color:var(--muted);margin:0 0 24px}'
        . '.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:24px;margin-bottom:20px}'
        . '.card h2{margin:0 0 16px;font-size:20px}'
        . 'table.checks{width:100%;border-collapse:collapse;font-size:14px}'
        . 'table.checks td{padding:8px 6px;border-bottom:1px solid var(--line);vertical-align:top}'
        . 'tr.help td{background:#fff8f0;color:#7a5b3a;font-size:13px}'
        . '.badge{display:inline-block;min-width:52px;text-align:center;font-size:11px;font-weight:700;'
        . 'padding:2px 8px;border-radius:20px;color:#fff}'
        . '.pass .badge{background:var(--green)}.fail .badge{background:var(--red)}'
        . '.muted{color:var(--muted)}.hint{display:block;font-size:13px;color:var(--muted);margin-top:4px}'
        . 'label{display:block;margin-bottom:14px;font-weight:600}'
        . 'label.checkbox{font-weight:400;display:flex;gap:8px;align-items:center}'
        . 'input[type=text],input[type=email],input[type=password],input[type=url]{display:block;width:100%;'
        . 'margin-top:6px;padding:10px 12px;font-size:15px;border:1px solid var(--line);border-radius:8px}'
        . '.btn{display:inline-block;background:var(--accent);color:#fff;border:0;border-radius:8px;'
        . 'padding:12px 24px;font-size:16px;font-weight:700;cursor:pointer;text-decoration:none}'
        . '.btn:hover{filter:brightness(1.08)}.btn[disabled]{opacity:.45;cursor:not-allowed}'
        . '.btn-ghost{background:#fff;color:var(--accent);border:2px solid var(--accent);margin-left:8px}'
        . '.errors{background:#fff0f0;border:1px solid #f5b5b5;color:#a61e1e;border-radius:8px;padding:12px 16px;margin-bottom:16px}'
        . '.errors ul{margin:8px 0 0;padding-left:20px}'
        . '.callout{background:#fff8f0;border-left:4px solid var(--accent);border-radius:0 8px 8px 0;'
        . 'padding:12px 16px;margin-top:16px;font-size:14px}'
        . '.success h2{color:var(--green)}code{background:#f1ede4;padding:2px 6px;border-radius:4px;font-size:13px}'
        . '</style></head><body><div class="wrap">'
        . '<header><h1>FOODIIE installer</h1><p>Set up your food site in under a minute.</p></header>'
        . $body
        . '</div></body></html>';
}
