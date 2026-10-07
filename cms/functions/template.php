<?php
/**
 * FOODIIE — template renderer used by the static generator (and admin preview).
 *
 * render('home', $vars) includes cms/templates/home.php with $vars extracted.
 * Every template also gets: $site, and helper functions u(), esc(), ad_html(),
 * seo_head(), jsonld_script(), public_image_url(), setting().
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/ads.php';
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/recipe_categories.php';

function site_info(): array
{
    return [
        'name' => setting('site_name', 'FOODIIE'),
        'tagline' => setting('site_tagline', 'Discover. Cook. Eat Better.'),
        'url' => app_url(),
        'base' => base_path(),
    ];
}

/** Render a template to string. */
function render(string $template, array $vars = []): string
{
    $file = FOODIIE_ROOT . '/cms/templates/' . $template . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Template not found: ' . $template);
    }
    $site = site_info();
    $vars['site'] = $site;
    // Always available to header/footer partials.
    if (!array_key_exists('nav_categories', $vars)) {
        $vars['nav_categories'] = nav_categories();
    }
    if (!array_key_exists('mega_menu', $vars)) {
        $vars['mega_menu'] = rc_mega_menu();
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $file;
    return (string) ob_get_clean();
}

/** Footer/explore navigation: all categories with base-aware URLs. */
function nav_categories(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        $rows = db()->query('SELECT name, slug FROM categories ORDER BY sort ASC, name ASC')->fetchAll();
    } catch (Throwable $e) {
        return $cache;
    }
    foreach ($rows as $r) {
        $cache[] = ['name' => (string) $r['name'], 'url' => u($r['slug'] . '/')];
    }
    return $cache;
}

/** Include a partial inside a template: partial('head', [...]). */
function partial(string $name, array $vars = []): void
{
    $file = FOODIIE_ROOT . '/cms/templates/partials/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Partial not found: ' . $name);
    }
    if (!array_key_exists('site', $vars)) {
        $vars['site'] = site_info();
    }
    if (!array_key_exists('mega_menu', $vars)) {
        $vars['mega_menu'] = rc_mega_menu();
    }
    extract($vars, EXTR_SKIP);
    include $file;
}

/** Write $html to public/<path>/index.html (creates dirs). Returns web path. */
function write_public(string $path, string $html): string
{
    $path = trim($path, '/');
    $dir = FOODIIE_ROOT . '/public/' . ($path === '' ? '' : $path . '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dir . 'index.html', $html, LOCK_EX);
    return $path === '' ? '/' : '/' . $path . '/';
}

/** Write a raw file into public/ (e.g. search-index.json, sitemap.xml). */
function write_public_file(string $relative, string $contents): void
{
    $full = FOODIIE_ROOT . '/public/' . ltrim($relative, '/');
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($full, $contents, LOCK_EX);
}
