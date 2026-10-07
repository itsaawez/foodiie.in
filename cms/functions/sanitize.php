<?php
/**
 * FOODIIE — HTML sanitizer (allowlist) for article/page bodies.
 *
 * Allowed: headings h2-h4, p, br, strong/b, em/i, ul/ol/li, links,
 * images, YouTube-nocookie iframes, tables, blockquotes, code,
 * figures, hr, and divs with a "callout" class for callout boxes.
 *
 * Everything else (script, style, event handlers, javascript: URLs,
 * data: URIs, objects, embeds) is stripped. Output is safe to echo raw.
 */

function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowed_tags = [
        'h2' => [], 'h3' => [], 'h4' => [],
        'p' => [], 'br' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'title', 'rel'],
        'img' => ['src', 'alt', 'title', 'loading', 'width', 'height'],
        'iframe' => ['src', 'title', 'loading', 'allowfullscreen', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'blockquote' => [], 'pre' => [], 'code' => [], 'hr' => [],
        'figure' => [], 'figcaption' => [],
        'div' => ['class'],
        'span' => [],
    ];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    // Wrap in a container so fragments parse reliably.
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__fd_root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $doc->getElementById('__fd_root');
    if (!$root) {
        return '';
    }

    sanitize_node($root, $allowed_tags);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function sanitize_node(DOMNode $node, array $allowed_tags): void
{
    // Iterate over a snapshot: children list mutates during cleaning.
    $children = [];
    foreach ($node->childNodes as $c) {
        $children[] = $c;
    }
    foreach ($children as $child) {
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);
            if (!array_key_exists($tag, $allowed_tags)) {
                // Drop dangerous tags entirely (with contents for script/style).
                if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta'], true)) {
                    $node->removeChild($child);
                } else {
                    // Unwrap: keep inner text/content, drop the tag.
                    $inner = [];
                    foreach ($child->childNodes as $gc) {
                        $inner[] = $gc;
                    }
                    foreach ($inner as $gc) {
                        $node->insertBefore($gc, $child);
                    }
                    $node->removeChild($child);
                    foreach ($inner as $gc) {
                        if ($gc instanceof DOMElement) {
                            sanitize_node($gc, $allowed_tags);
                        }
                    }
                }
                continue;
            }
            sanitize_attributes($child, $tag, $allowed_tags[$tag]);
            sanitize_node($child, $allowed_tags);
        } elseif ($child instanceof DOMComment) {
            $node->removeChild($child);
        }
    }
}

function sanitize_attributes(DOMElement $el, string $tag, array $allowed_attrs): void
{
    // Remove everything not on the allowlist (kills on* handlers, style, etc.).
    $to_remove = [];
    foreach ($el->attributes as $attr) {
        $name = strtolower($attr->name);
        if (!in_array($name, $allowed_attrs, true)) {
            $to_remove[] = $attr->name;
        }
    }
    foreach ($to_remove as $name) {
        $el->removeAttribute($name);
    }

    // Validate href/src values.
    foreach (['href', 'src'] as $attr) {
        if ($el->hasAttribute($attr)) {
            $val = trim($el->getAttribute($attr));
            if (!sanitize_url_value($tag, $attr, $val)) {
                $el->removeAttribute($attr);
            } else {
                // Force safe rel on external links; normalize lazy loading on images.
                if ($tag === 'a' && preg_match('#^https?://#i', $val)) {
                    $el->setAttribute('rel', 'noopener noreferrer');
                    $el->setAttribute('target', '_blank');
                }
            }
        }
    }

    if ($tag === 'img') {
        if (!$el->hasAttribute('alt')) {
            $el->setAttribute('alt', '');
        }
        $el->setAttribute('loading', 'lazy');
    }
    if ($tag === 'iframe') {
        $el->setAttribute('loading', 'lazy');
        if (!$el->hasAttribute('title')) {
            $el->setAttribute('title', 'Embedded video');
        }
    }
    if ($tag === 'div') {
        // Only callout classes survive; anything else is unwrapped by the caller.
        $class = $el->getAttribute('class');
        if (strpos($class, 'callout') === false) {
            // Replace div with its children.
            $parent = $el->parentNode;
            if ($parent) {
                $inner = [];
                foreach ($el->childNodes as $gc) {
                    $inner[] = $gc;
                }
                foreach ($inner as $gc) {
                    $parent->insertBefore($gc, $el);
                }
                $parent->removeChild($el);
            }
        }
    }
}

/** True when the URL value is acceptable for the given tag/attribute. */
function sanitize_url_value(string $tag, string $attr, string $val): bool
{
    if ($val === '') {
        return false;
    }
    $lower = strtolower($val);
    // Block dangerous schemes.
    if (preg_match('#^\s*(javascript|data|vbscript|file):#i', $lower)) {
        return false;
    }
    if ($tag === 'iframe') {
        // Only YouTube nocookie embeds.
        return (bool) preg_match('#^https://www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{6,}#', $val);
    }
    if ($tag === 'img') {
        // Relative uploads, site assets, or http(s) images.
        return (bool) preg_match('#^(https?://|/|assets/|uploads/)#i', $val)
            && !preg_match('#\.(php|phtml|svg)(\?|$)#i', $val);
    }
    if ($tag === 'a') {
        // Allow http(s), site-relative, anchors, mailto.
        return (bool) preg_match('#^(https?://|/|\#|mailto:)#i', $val);
    }
    return true;
}

/** Extract a YouTube video ID from any common YouTube URL or raw ID. Returns '' if none. */
function youtube_id(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
        return $url;
    }
    $patterns = [
        '#youtu\.be/([A-Za-z0-9_-]{11})#i',
        '#[?&]v=([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/embed/([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/shorts/([A-Za-z0-9_-]{11})#i',
        '#youtube-nocookie\.com/embed/([A-Za-z0-9_-]{11})#i',
        // Fallback for 6-12 chars legacy
        '#youtu\.be/([A-Za-z0-9_-]{6,})#i',
        '#[?&]v=([A-Za-z0-9_-]{6,})#i',
        '#youtube\.com/shorts/([A-Za-z0-9_-]{6,})#i',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $url, $m)) {
            return $m[1];
        }
    }
    return '';
}

/** Check if string is a valid YouTube video ID. */
function is_valid_youtube_id(string $id): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{6,15}$/', trim($id));
}

/** Detect video type: normal (16:9) or shorts (9:16). */
function youtube_type(string $url): string
{
    if (stripos($url, '/shorts/') !== false) {
        return 'shorts';
    }
    return 'normal';
}

/** Official watch link on YouTube. */
function youtube_watch_url(string $video_id, string $type = 'normal'): string
{
    $video_id = trim($video_id);
    if ($type === 'shorts') {
        return 'https://www.youtube.com/shorts/' . $video_id;
    }
    return 'https://www.youtube.com/watch?v=' . $video_id;
}

/** Privacy-enhanced embed URL for a video ID. */
function youtube_embed_url(string $video_id): string
{
    return 'https://www.youtube-nocookie.com/embed/' . $video_id . '?rel=0';
}
