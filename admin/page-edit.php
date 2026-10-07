<?php
/** FOODIIE admin — edit a static page. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id');
$stmt->execute([':id' => $id]);
$page = $stmt->fetch();
if (!$page) {
    flash('error', 'Page not found.');
    redirect('pages.php');
}

$legal = ['privacy-policy', 'terms', 'disclaimer', 'cookie-policy', 'affiliate-disclosure'];
$is_legal = in_array($page['slug'], $legal, true);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $title = trim($_POST['title'] ?? '');
    $body = sanitize_html($_POST['body'] ?? '');
    $seo_title = trim($_POST['seo_title'] ?? '');
    $seo_description = trim($_POST['seo_description'] ?? '');
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if (!$errors) {
        $pdo->prepare('UPDATE pages SET title=:t, body=:b, seo_title=:st, seo_description=:sd, updated_at=:u WHERE id=:id')
            ->execute([':t' => $title, ':b' => $body, ':st' => $seo_title, ':sd' => $seo_description,
                ':u' => now_utc(), ':id' => $id]);
        [$ok, $msg] = regen_site();
        flash($ok ? 'success' : 'warning', 'Page saved. ' . $msg);
        redirect('pages.php');
    }
    $page['title'] = $title;
    $page['body'] = $body;
    $page['seo_title'] = $seo_title;
    $page['seo_description'] = $seo_description;
}

admin_head('Edit Page', 'pages', $current_user);
?>

<?php if ($is_legal): ?>
  <div class="legal-notice">
    <strong>TEMPLATE — requires legal review before production use.</strong><br>
    This page ships as a generic template. Have it reviewed by a qualified legal professional before publishing.
  </div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?php echo esc($e); ?></div>
<?php endforeach; ?>

<div class="panel">
  <h2><?php echo esc($page['title']); ?> <span class="muted">(<?php echo esc($page['slug']); ?>)</span></h2>
  <form method="post" action="page-edit.php?id=<?php echo $id; ?>">
    <?php echo csrf_field(); ?>
    <div class="field">
      <label for="p-title">Title *</label>
      <input type="text" id="p-title" name="title" value="<?php echo esc($page['title']); ?>" required>
    </div>
    <div class="field">
      <label for="p-body">Body (HTML allowed)</label>
      <textarea id="p-body" name="body" class="tall"><?php echo esc($page['body']); ?></textarea>
      <div class="hint">HTML is sanitized on save.</div>
    </div>
    <div class="form-row">
      <div class="field">
        <label for="p-seo-t">SEO title</label>
        <input type="text" id="p-seo-t" name="seo_title" value="<?php echo esc($page['seo_title']); ?>">
      </div>
      <div class="field">
        <label for="p-seo-d">SEO description</label>
        <input type="text" id="p-seo-d" name="seo_description" value="<?php echo esc($page['seo_description']); ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save page</button>
    <a class="btn btn-ghost" href="pages.php">Cancel</a>
  </form>
</div>

<?php admin_foot(); ?>
