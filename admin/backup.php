<?php
/** FOODIIE admin — database + data backup (ZIP download). */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$backup_dir = storage_path('backups');
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// ---------- build a new backup ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!class_exists('ZipArchive')) {
        flash('error', 'ZipArchive is not available on this server.');
        redirect('backup.php');
    }

    $fname = 'backup-' . date('Ymd-His') . '.zip';
    $path = $backup_dir . '/' . $fname;

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE) !== true) {
        flash('error', 'Could not create the backup file.');
        redirect('backup.php');
    }

    $db_file = db_path();
    if (is_readable($db_file)) {
        $zip->addFile($db_file, 'foodiie.sqlite');
    }

    // Settings snapshot.
    try {
        $settings = db()->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $zip->addFromString('settings.json', json_encode($settings ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {
        $zip->addFromString('settings.json', '{}');
    }

    foreach (['newsletter.csv', 'contact.csv'] as $csv) {
        $p = storage_path($csv);
        if (is_readable($p)) {
            $zip->addFile($p, $csv);
        }
    }
    $zip->close();

    if (!is_file($path)) {
        flash('error', 'Backup failed.');
        redirect('backup.php');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// ---------- list existing backups ----------
$backups = [];
foreach (glob($backup_dir . '/backup-*.zip') ?: [] as $f) {
    if (preg_match('/^backup-\d{8}-\d{6}\.zip$/', basename($f))) {
        $backups[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
    }
}
usort($backups, function ($a, $b) { return $b['time'] <=> $a['time']; });

$db_size = is_file(db_path()) ? filesize(db_path()) : 0;

admin_head('Backup', 'backup', $current_user);
?>

<div class="panel">
  <h2>Create a backup</h2>
  <dl class="kv">
    <dt>Database</dt><dd><code>cms/data/foodiie.sqlite</code> (<?php echo number_format($db_size / 1024, 1); ?> KB)</dd>
    <dt>Included</dt><dd>SQLite database, settings JSON<?php echo is_readable(storage_path('newsletter.csv')) ? ', newsletter.csv' : ''; ?><?php echo is_readable(storage_path('contact.csv')) ? ', contact.csv' : ''; ?></dd>
    <dt>Stored in</dt><dd><code>storage/backups/</code></dd>
  </dl>
  <form method="post" action="backup.php" style="margin-top:1rem">
    <?php echo csrf_field(); ?>
    <button type="submit" class="btn btn-primary">Create &amp; download backup</button>
  </form>
</div>

<div class="panel">
  <h2>Existing backups</h2>
  <?php if (!$backups): ?>
    <p class="muted">No backups yet.</p>
  <?php else: ?>
    <?php foreach ($backups as $b): ?>
      <div class="list-row">
        <span><?php echo esc($b['name']); ?> <span class="muted">(<?php echo number_format($b['size'] / 1024, 1); ?> KB &middot; <?php echo esc(date('j M Y H:i', $b['time'])); ?>)</span></span>
        <a class="btn btn-secondary btn-sm" href="backup-download.php?f=<?php echo esc(urlencode($b['name'])); ?>">Download</a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php admin_foot(); ?>
