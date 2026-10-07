<?php
/**
 * FOODIIE — <head> partial.
 * Vars: $page (title, description, canonical path, og_image, type, robots),
 *       $jsonld (array of LD arrays), $site.
 * Outputs everything up to and including the <body data-base="..."> tag.
 */

if (!function_exists('fd_public_url')) {
    /**
     * Normalize a content URL to a base-path-aware public URL.
     * Passes through absolute URLs; avoids double-prefixing u()-built URLs.
     */
    function fd_public_url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return '#';
        }
        if (preg_match('#^(https?://|mailto:|tel:|\#)#i', $url)) {
            return $url;
        }
        $base = base_path();
        if ($base !== '' && str_starts_with($url, $base . '/')) {
            return $url;
        }
        return u(ltrim($url, '/'));
    }
}

$gsc = trim(setting('google_search_console_verification', ''));
$ga_enabled = setting('ga_enabled', '') === '1';
$ga_id = trim(setting('google_analytics_id', ''));
$px_enabled = setting('meta_pixel_enabled', '') === '1';
$px_id = trim(setting('meta_pixel_id', ''));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= seo_head($page ?? []) ?>
<link rel="icon" type="image/svg+xml" href="<?= esc(u('favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= esc(u('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= esc(u('assets/css/print.css')) ?>" media="print">
<script defer src="<?= esc(u('assets/js/main.js')) ?>"></script>
<?php if ($gsc !== ''): ?>
<meta name="google-site-verification" content="<?= esc($gsc) ?>">
<?php endif; ?>
<?php if ($ga_enabled && $ga_id !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= esc($ga_id) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?= esc($ga_id) ?>');
</script>
<?php endif; ?>
<?php if ($px_enabled && $px_id !== ''): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= esc($px_id) ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?= esc($px_id) ?>&amp;ev=PageView&amp;noscript=1"></noscript>
<?php endif; ?>
<?php foreach (($jsonld ?? []) as $ld): ?>
<?= jsonld_script($ld) ?>
<?php endforeach; ?>
</head>
<body data-base="<?= esc(base_path()) ?>">
