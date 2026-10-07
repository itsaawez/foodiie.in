<?php
/**
 * FOODIIE — SEO helpers: meta tags + JSON-LD builders.
 *
 * $page array keys used by seo_head():
 *   title, description, canonical (path), og_image (public URL path or absolute),
 *   type ('website'|'article'), robots (default 'index, follow')
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/** Render the <head> meta block for a page. Echo-safe (already escaped). */
function seo_head(array $page): string
{
    $title = trim($page['title'] ?? '');
    $desc = trim($page['description'] ?? '');
    $canon_path = $page['canonical'] ?? '/';
    $canonical = str_starts_with($canon_path, 'http') ? $canon_path : abs_url($canon_path);
    $og_image = $page['og_image'] ?? '';
    if ($og_image !== '' && !str_starts_with($og_image, 'http')) {
        $og_image = abs_media_url($og_image);
    }
    $type = $page['type'] ?? 'website';
    $robots = $page['robots'] ?? 'index, follow';
    $site = setting('site_name', 'FOODIIE');

    $h = '';
    $h .= '<title>' . esc($title) . "</title>\n";
    if ($desc !== '') {
        $h .= '<meta name="description" content="' . esc($desc) . "\">\n";
    }
    $h .= '<meta name="robots" content="' . esc($robots) . "\">\n";
    $h .= '<link rel="canonical" href="' . esc($canonical) . "\">\n";
    // Open Graph
    $h .= '<meta property="og:site_name" content="' . esc($site) . "\">\n";
    $h .= '<meta property="og:type" content="' . esc($type) . "\">\n";
    $h .= '<meta property="og:title" content="' . esc($title) . "\">\n";
    if ($desc !== '') {
        $h .= '<meta property="og:description" content="' . esc($desc) . "\">\n";
    }
    $h .= '<meta property="og:url" content="' . esc($canonical) . "\">\n";
    if ($og_image !== '') {
        $h .= '<meta property="og:image" content="' . esc($og_image) . "\">\n";
    }
    // Twitter/X
    $h .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $h .= '<meta name="twitter:title" content="' . esc($title) . "\">\n";
    if ($desc !== '') {
        $h .= '<meta name="twitter:description" content="' . esc($desc) . "\">\n";
    }
    if ($og_image !== '') {
        $h .= '<meta name="twitter:image" content="' . esc($og_image) . "\">\n";
    }
    return $h;
}

/** <script type="application/ld+json"> wrapper. */
function jsonld_script(array $data): string
{
    return '<script type="application/ld+json">' .
        json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
        "</script>\n";
}

function jsonld_website(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => setting('site_name', 'FOODIIE'),
        'url' => app_url() . '/',
        'description' => setting('site_tagline', 'Discover. Cook. Eat Better.'),
    ];
}

function jsonld_organization(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => setting('site_name', 'FOODIIE'),
        'url' => app_url() . '/',
        'logo' => abs_url('assets/icons/logo.svg'),
    ];
}

function jsonld_breadcrumb(array $items): array
{
    // $items: [['name'=>..,'url'=>path], ...]
    $list = [];
    $pos = 1;
    foreach ($items as $it) {
        $entry = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $it['name']];
        if (!empty($it['url'])) {
            $entry['item'] = str_starts_with($it['url'], 'http') ? $it['url'] : abs_url($it['url']);
        }
        $list[] = $entry;
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

/** Article JSON-LD. Only real fields. */
function jsonld_article(array $article, string $url_path, string $category_name, string $author_name): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $article['title'],
        'description' => $article['description'] ?: excerpt($article['body']),
        'url' => abs_url($url_path),
        'datePublished' => iso_date($article['publish_at'] ?: $article['created_at']),
        'dateModified' => iso_date($article['updated_at']),
        'author' => ['@type' => 'Person', 'name' => $author_name],
        'publisher' => [
            '@type' => 'Organization',
            'name' => setting('site_name', 'FOODIIE'),
            'logo' => ['@type' => 'ImageObject', 'url' => abs_url('assets/icons/logo.svg')],
        ],
    ];
    if ($article['image'] !== '') {
        $data['image'] = [abs_media_url(public_image_url($article['image']))];
    }
    return $data;
}

/**
 * Recipe JSON-LD. ONLY fields that actually exist are output —
 * never invent nutrition values.
 */
function jsonld_recipe(array $recipe, string $url_path): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => $recipe['name'],
        'url' => abs_url($url_path),
        'description' => $recipe['description'] ?: '',
        'datePublished' => iso_date($recipe['publish_at'] ?: $recipe['created_at']),
        'author' => ['@type' => 'Organization', 'name' => setting('site_name', 'FOODIIE')],
    ];
    if ($recipe['image'] !== '') {
        $data['image'] = [abs_media_url(public_image_url($recipe['image']))];
    }
    foreach (['prep_time' => 'prepTime', 'cook_time' => 'cookTime', 'total_time' => 'totalTime'] as $col => $ld) {
        if (trim((string) $recipe[$col]) !== '') {
            $data[$ld] = iso8601_duration((string) $recipe[$col]);
        }
    }
    if (trim((string) $recipe['servings']) !== '') {
        $data['recipeYield'] = $recipe['servings'];
    }
    if (trim((string) $recipe['difficulty']) !== '') {
        // Not a schema.org property; keep as keywords-adjacent note instead.
        $data['keywords'] = trim(trim((string) ($data['keywords'] ?? '')) . ' ' . $recipe['difficulty']);
    }
    if (trim((string) $recipe['cuisine']) !== '') {
        $data['recipeCuisine'] = $recipe['cuisine'];
    }
    $ingredients = json_decode((string) $recipe['ingredients'], true);
    if (is_array($ingredients) && $ingredients) {
        $data['recipeIngredient'] = array_values(array_filter(array_map('trim', $ingredients)));
    }
    $instructions = json_decode((string) $recipe['instructions'], true);
    if (is_array($instructions) && $instructions) {
        $steps = [];
        $pos = 1;
        foreach ($instructions as $step) {
            $step = trim((string) $step);
            if ($step === '') {
                continue;
            }
            $steps[] = ['@type' => 'HowToStep', 'position' => $pos++, 'text' => $step];
        }
        if ($steps) {
            $data['recipeInstructions'] = $steps;
        }
    }
    // Nutrition: only provided values, no invention.
    $nutrition = [];
    foreach (['calories' => 'calories', 'protein' => 'proteinContent', 'carbs' => 'carbohydrateContent', 'fat' => 'fatContent'] as $col => $ld) {
        $v = trim((string) $recipe[$col]);
        if ($v !== '') {
            $nutrition[$ld] = $v;
        }
    }
    if ($nutrition) {
        $nutrition['@type'] = 'NutritionInformation';
        $data['nutrition'] = $nutrition;
    }
    $tags = parse_tags((string) $recipe['tags']);
    if ($tags) {
        $kw = trim((string) ($data['keywords'] ?? ''));
        $data['keywords'] = trim($kw . ' ' . implode(', ', $tags));
    }
    // YouTube recipe video integration (Schema.org VideoObject)
    $vid = trim((string) ($recipe['youtube_video_id'] ?? ''));
    $venabled = (string) ($recipe['youtube_enabled'] ?? '1') !== '0';
    if ($vid !== '' && $venabled) {
        $video_name = trim((string) ($recipe['youtube_video_title'] ?? '')) ?: $recipe['name'] . ' Recipe Video';
        $video_desc = trim((string) ($recipe['youtube_video_description'] ?? '')) ?: ($recipe['description'] ?: $recipe['name']);
        $video_obj = [
            '@type' => 'VideoObject',
            'name' => $video_name,
            'description' => $video_desc,
            'thumbnailUrl' => [
                'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg'
            ],
            'embedUrl' => youtube_embed_url($vid),
        ];
        $channel = trim((string) ($recipe['youtube_channel_name'] ?? ''));
        if ($channel !== '') {
            $video_obj['author'] = [
                '@type' => 'Person',
                'name' => $channel,
            ];
        }
        $data['video'] = $video_obj;
    }

    return $data;
}

/** Video JSON-LD. */
function jsonld_video(array $video, string $url_path): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'VideoObject',
        'name' => $video['title'],
        'description' => $video['description'] ?: $video['title'],
        'url' => abs_url($url_path),
        'embedUrl' => youtube_embed_url($video['video_id']),
        'uploadDate' => iso_date($video['publish_at'] ?: $video['created_at']),
    ];
    $thumb = video_thumbnail_url($video);
    if ($thumb !== '') {
        $data['thumbnailUrl'] = [abs_media_url($thumb)];
    }
    return $data;
}

/** Convert "30 mins" / "1 hr" style input to ISO 8601 duration; pass through if already ISO. */
function iso8601_duration(string $input): string
{
    $input = trim($input);
    if (preg_match('/^PT/i', $input)) {
        return strtoupper($input);
    }
    if (preg_match('/^P/i', $input)) {
        return $input;
    }
    $mins = 0;
    if (preg_match('/(\d+(?:\.\d+)?)\s*(h|hr|hrs|hour|hours)/i', $input, $m)) {
        $mins += (float) $m[1] * 60;
    }
    if (preg_match('/(\d+(?:\.\d+)?)\s*(m|min|mins|minute|minutes)/i', $input, $m)) {
        $mins += (float) $m[1];
    }
    if ($mins <= 0 && is_numeric($input)) {
        $mins = (float) $input; // assume minutes
    }
    if ($mins <= 0) {
        return $input; // unknown format: pass through untouched
    }
    $mins = (int) round($mins);
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    $out = 'PT';
    if ($h > 0) {
        $out .= $h . 'H';
    }
    if ($m > 0 || $h === 0) {
        $out .= $m . 'M';
    }
    return $out;
}

/**
 * Public URL for a content image.
 * $stored is like 'uploads/2026/09/name.jpg' (storage/uploads/...) or an
 * absolute http(s) URL. Generator copies storage uploads into
 * public/assets/uploads/, so we map to u('assets/uploads/...').
 */
function public_image_url(string $stored): string
{
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }
    if (str_starts_with($stored, 'http')) {
        return $stored;
    }
    if (str_starts_with($stored, 'uploads/')) {
        return u('assets/' . $stored);
    }
    return u(ltrim($stored, '/'));
}

/**
 * Absolute URL for an image/asset path that may already carry the base
 * path (u() output) or be a bare relative path. Avoids double-prefixing
 * when the site lives in a subfolder: app_url() already ends with the
 * base path.
 */
function abs_media_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('#^https?://#i', $url)) {
        return $url;
    }
    $p = '/' . ltrim($url, '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($p, $base . '/')) {
        $p = substr($p, strlen($base));
    }
    return abs_url($p);
}

/** Thumbnail URL for a video: uploaded thumb, else YouTube thumbnail. */
function video_thumbnail_url(array $video): string
{
    if (!empty($video['thumbnail']) && !str_starts_with($video['thumbnail'], 'uploads/')) {
        return trim($video['thumbnail']);
    }
    if (!empty($video['thumbnail'])) {
        return public_image_url($video['thumbnail']);
    }
    if (!empty($video['video_id'])) {
        return 'https://i.ytimg.com/vi/' . $video['video_id'] . '/hqdefault.jpg';
    }
    return '';
}
