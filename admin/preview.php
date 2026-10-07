<?php
/** FOODIIE admin — render a live preview of an article/recipe/video (any status). */
require_once __DIR__ . '/includes/guard.php';

$types = [
    'article' => ['articles', 'render_article_page'],
    'recipe' => ['recipes', 'render_recipe_page'],
    'video' => ['videos', 'render_video_page'],
];

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if (!isset($types[$type]) || $id <= 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid preview request.';
    exit;
}

[$table, $render_fn] = $types[$type];
$stmt = db()->prepare("SELECT * FROM {$table} WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found.';
    exit;
}

$gen = dirname(__DIR__) . '/cms/generator/generate.php';
if (is_readable($gen)) {
    require_once $gen;
}

if (!function_exists($render_fn)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Preview unavailable</title></head>
<body style="font-family:sans-serif;padding:2rem">
<h1>Preview unavailable</h1>
<p>The site generator has not been built yet, so live previews are not possible right now.
The content is saved — come back once the generator exists.</p>
<p><a href="dashboard.php">Back to dashboard</a></p>
</body></html>
    <?php
    exit;
}

// Mark as previewed so the content checklist can credit it.
$_SESSION['previewed'][$type][$id] = true;

$banner = '<!-- PREVIEW — not published, visible to logged-in admins only -->'
    . '<div style="position:fixed;top:0;left:0;right:0;z-index:99999;background:#b45309;color:#fff;'
    . 'text-align:center;padding:10px 16px;font:600 14px/1.4 -apple-system,Segoe UI,Roboto,sans-serif;">'
    . 'PREVIEW — not published. Only visible to logged-in admins.</div>';

echo $banner;
echo call_user_func($render_fn, $row);
exit;
