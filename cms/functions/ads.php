<?php
/**
 * FOODIIE — advertisement slot resolution.
 *
 * Slots: header, homepage, article_top, article_middle, article_bottom,
 *        sidebar, footer.
 * Each slot: enabled/disabled, ad code, device (desktop|mobile|both),
 * start/end dates. Disabled or empty slots render an HTML comment only —
 * never a visible placeholder, never deceptive.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function ad_slots(): array
{
    return [
        'header' => 'Header Ad',
        'homepage' => 'Homepage Ad',
        'article_top' => 'Article Top Ad',
        'article_middle' => 'Article Middle Ad',
        'article_bottom' => 'Article Bottom Ad',
        'sidebar' => 'Sidebar Ad',
        'footer' => 'Footer Ad',
    ];
}

/** All ad rows keyed by slot. */
function get_ads(): array
{
    static $ads = null;
    if ($ads !== null) {
        return $ads;
    }
    $ads = [];
    try {
        foreach (db()->query('SELECT * FROM ads')->fetchAll() as $row) {
            $ads[$row['slot']] = $row;
        }
    } catch (Throwable $e) {
        // DB not installed yet (installer) — no ads.
    }
    return $ads;
}

/**
 * Render an ad slot. Returns '' (with an HTML comment) when disabled,
 * empty, or outside its date range. Device targeting is done with CSS
 * classes since the public site is static.
 */
function ad_html(string $slot): string
{
    $ads = get_ads();
    $ad = $ads[$slot] ?? null;
    if (!$ad || !(int) $ad['enabled'] || trim((string) $ad['code']) === '') {
        return "<!-- ad slot: {$slot} (disabled) -->";
    }
    $today = date('Y-m-d');
    if ($ad['start_date'] !== '' && $ad['start_date'] > $today) {
        return "<!-- ad slot: {$slot} (not started) -->";
    }
    if ($ad['end_date'] !== '' && $ad['end_date'] < $today) {
        return "<!-- ad slot: {$slot} (expired) -->";
    }
    $device = in_array($ad['device'], ['desktop', 'mobile', 'both'], true) ? $ad['device'] : 'both';
    // Ad code is admin-provided trusted HTML (only admins can edit it).
    return '<div class="ad ad-' . esc($slot) . ' ad-' . esc($device) . '" role="complementary" aria-label="Advertisement">'
        . $ad['code'] . '</div>';
}
