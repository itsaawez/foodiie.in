<?php
/** FOODIIE admin — media library: upload + browse + delete. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../cms/functions/media.php';

$pdo = db();

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$ok, $msg] = handle_upload($_FILES['file'], trim($_POST['alt'] ?? ''));
            flash($ok ? 'success' : 'error', $msg);
        } else {
            flash('error', 'Choose a file to upload.');
        }
        redirect('media.php');
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        if (delete_media($id)) {
            flash('success', 'Media deleted.');
        } else {
            flash('error', 'Media not found.');
        }
        redirect('media.php');
    }
}

// ---------- list ----------
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare('SELECT * FROM media WHERE filename LIKE :q OR original_name LIKE :q ORDER BY created_at DESC');
    $stmt->execute([':q' => '%' . $q . '%']);
    $rows = $stmt->fetchAll();
} else {
    $rows = $pdo->query('SELECT * FROM media ORDER BY created_at DESC')->fetchAll();
}

admin_head('Media Library', 'media', $current_user);
?>

<div class="panel">
  <h2>Upload image</h2>
  <form method="post" action="media.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="upload">
    <div class="form-row">
      <div class="field">
        <label for="m-file">File</label>
        <input type="file" id="m-file" name="file" accept="image/*" required>
        <div class="hint">JPG, PNG, WebP, AVIF or GIF, max 8 MB.</div>
      </div>
      <div class="field">
        <label for="m-alt">Alt text</label>
        <input type="text" id="m-alt" name="alt" placeholder="Describe the image…">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
  <p class="hint">Note: uploaded images go live on the public site at the next regeneration.</p>
</div>

<form method="get" action="media.php" class="filters">
  <div class="field">
    <label for="mq">Search by filename</label>
    <input type="text" id="mq" name="q" value="<?php echo esc($q); ?>" placeholder="e.g. biryani.jpg">
  </div>
  <div class="field">
    <button type="submit" class="btn btn-secondary">Search</button>
    <?php if ($q !== ''): ?>
      <a class="btn btn-ghost" href="media.php">Clear</a>
    <?php endif; ?>
  </div>
</form>

<?php if (!$rows): ?>
  <p class="muted">No media yet.</p>
<?php else: ?>
<div class="media-grid">
  <?php foreach ($rows as $m): ?>
    <div class="media-card">
      <img src="media-view.php?id=<?php echo (int) $m['id']; ?>" alt="<?php echo esc($m['alt'] ?: $m['original_name']); ?>" loading="lazy">
      <div class="m-body">
        <div class="m-name"><?php echo esc($m['original_name']); ?></div>
        <div class="m-meta">
          <?php echo esc($m['filename']); ?><br>
          <?php echo number_format((int) $m['size'] / 1024, 1); ?> KB
          &middot; <?php echo (int) $m['width']; ?>&times;<?php echo (int) $m['height']; ?><br>
          <?php echo esc(fmt_date($m['created_at'])); ?>
        </div>
      </div>
      <div class="m-actions">
        <button type="button" class="btn btn-ghost btn-sm copy-url" data-url="../public/assets/uploads/<?php echo esc($m['filename']); ?>">Copy URL</button>
        <?php echo row_btn('delete', (int) $m['id'], 'Delete', 'btn-danger', 'Delete this image permanently?'); ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<p class="muted"><?php echo count($rows); ?> file(s)</p>
<?php endif; ?>

<script>
document.querySelectorAll('.copy-url').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var url = btn.getAttribute('data-url');
    var done = function () {
      btn.textContent = 'Copied!';
      setTimeout(function () { btn.textContent = 'Copy URL'; }, 1500);
    };
    var fallback = function () {
      var t = document.createElement('textarea');
      t.value = url;
      document.body.appendChild(t);
      t.select();
      try { document.execCommand('copy'); done(); } catch (e) {}
      document.body.removeChild(t);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done, fallback);
    } else {
      fallback();
    }
  });
});
</script>

<?php admin_foot(); ?>
