<?php
/** FOODIIE admin — video list with filters, bulk actions, pagination. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $qs = $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';

    if (in_array($action, ['delete', 'toggle', 'duplicate'], true) && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT * FROM videos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('error', 'Video not found.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM videos WHERE id = :id')->execute([':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Video deleted. ' . $msg);
        } elseif ($action === 'toggle') {
            $new = $row['status'] === 'published' ? 'draft' : 'published';
            $pdo->prepare('UPDATE videos SET status = :s, updated_at = :u WHERE id = :id')
                ->execute([':s' => $new, ':u' => now_utc(), ':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', ($new === 'published' ? 'Video published. ' : 'Video unpublished. ') . $msg);
        } else { // duplicate
            $now = now_utc();
            $pdo->prepare('INSERT INTO videos(title, slug, youtube_url, video_id, thumbnail, description, category_id, tags,
                status, publish_at, created_at, updated_at, seo_title, seo_description)
                VALUES(:title, :slug, :youtube_url, :video_id, :thumbnail, :description, :category_id, :tags,
                :status, NULL, :c, :u, :seo_title, :seo_description)')->execute([
                ':title' => $row['title'] . ' (Copy)',
                ':slug' => unique_slug($pdo, 'videos', $row['slug'] . '-copy'),
                ':youtube_url' => $row['youtube_url'], ':video_id' => $row['video_id'],
                ':thumbnail' => $row['thumbnail'], ':description' => $row['description'],
                ':category_id' => $row['category_id'], ':tags' => $row['tags'],
                ':status' => 'draft', ':c' => $now, ':u' => $now,
                ':seo_title' => $row['seo_title'], ':seo_description' => $row['seo_description'],
            ]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Video duplicated as draft. ' . $msg);
        }
        redirect('videos.php' . $qs);
    }

    if ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $bulk = $_POST['bulk_action'] ?? '';
        if ($ids && in_array($bulk, ['publish', 'unpublish', 'delete'], true)) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulk === 'delete') {
                $pdo->prepare("DELETE FROM videos WHERE id IN ($marks)")->execute($ids);
            } else {
                $st = $bulk === 'publish' ? 'published' : 'draft';
                $pdo->prepare("UPDATE videos SET status = ?, updated_at = ? WHERE id IN ($marks)")
                    ->execute(array_merge([$st, now_utc()], $ids));
            }
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Bulk ' . $bulk . ' applied to ' . count($ids) . ' video(s). ' . $msg);
        } else {
            flash('warning', 'Nothing selected, or unknown bulk action.');
        }
        redirect('videos.php' . $qs);
    }
}

// ---------- filters + pagination ----------
$q = trim($_GET['q'] ?? '');
$fstatus = $_GET['status'] ?? '';
$fcat = (int) ($_GET['cat'] ?? 0);
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(v.title LIKE :q OR v.description LIKE :q OR v.tags LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$valid_status = ['draft', 'review', 'scheduled', 'published', 'archived'];
if (in_array($fstatus, $valid_status, true)) {
    $where[] = 'v.status = :st';
    $params[':st'] = $fstatus;
}
if ($fcat > 0) {
    $where[] = 'v.category_id = :cat';
    $params[':cat'] = $fcat;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM videos v' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare('SELECT v.*, c.name AS category_name
    FROM videos v LEFT JOIN categories c ON c.id = v.category_id'
    . $whereSql . ' ORDER BY v.updated_at DESC LIMIT :lim OFFSET :off');
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$cats = $pdo->query('SELECT id, name FROM categories ORDER BY sort, name')->fetchAll();

function vfilter_qs(array $over = []): string
{
    $p = ['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? '', 'cat' => $_GET['cat'] ?? ''];
    foreach ($over as $k => $v) {
        $p[$k] = $v;
    }
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return $p ? '?' . http_build_query($p) : '';
}

admin_head('Videos', 'videos', $current_user);
?>

<form method="get" action="videos.php" class="filters">
  <div class="field">
    <label for="fq">Search</label>
    <input type="text" id="fq" name="q" value="<?php echo esc($q); ?>" placeholder="Title, description, tags…">
  </div>
  <div class="field">
    <label for="fstatus">Status</label>
    <select id="fstatus" name="status">
      <option value="">All statuses</option>
      <?php echo status_options($fstatus); ?>
    </select>
  </div>
  <div class="field">
    <label for="fcat">Category</label>
    <select id="fcat" name="cat">
      <option value="">All categories</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?php echo (int) $c['id']; ?>"<?php echo $fcat === (int) $c['id'] ? ' selected' : ''; ?>><?php echo esc($c['name']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($q !== '' || $fstatus !== '' || $fcat > 0): ?>
      <a class="btn btn-ghost" href="videos.php">Clear</a>
    <?php endif; ?>
  </div>
</form>

<form id="bulkform" method="post" action="videos.php<?php echo vfilter_qs(); ?>">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="bulk">
  <div class="toolbar">
    <select name="bulk_action" aria-label="Bulk action">
      <option value="">Bulk actions…</option>
      <option value="publish">Publish</option>
      <option value="unpublish">Unpublish</option>
      <option value="delete">Delete</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Apply this bulk action to the selected videos?')">Apply</button>
    <span class="spacer"></span>
    <a class="btn btn-primary" href="video-edit.php">+ Add Video</a>
  </div>
</form>

<div class="table-wrap">
<table class="tbl">
  <thead>
    <tr>
      <th><input type="checkbox" id="check-all" aria-label="Select all"></th>
      <th>Title</th><th>Category</th><th>Status</th><th>Published</th><th>Updated</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="muted">No videos found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><input type="checkbox" name="ids[]" value="<?php echo (int) $r['id']; ?>" form="bulkform"></td>
      <td class="row-title">
        <a href="video-edit.php?id=<?php echo (int) $r['id']; ?>"><?php echo esc($r['title']); ?></a>
        <?php if (strpos((string) $r['title'], '[Demo]') !== false): ?>
          <span class="badge b-demo">Demo content</span>
        <?php endif; ?>
      </td>
      <td class="muted"><?php echo esc($r['category_name'] ?? '—'); ?></td>
      <td><?php echo status_badge((string) $r['status']); ?></td>
      <td class="muted"><?php echo $r['publish_at'] ? esc(fmt_date($r['publish_at'])) : '—'; ?></td>
      <td class="muted"><?php echo esc(fmt_date($r['updated_at'])); ?></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="video-edit.php?id=<?php echo (int) $r['id']; ?>">Edit</a>
        <a class="btn btn-ghost btn-sm" href="preview.php?type=video&id=<?php echo (int) $r['id']; ?>" target="_blank" rel="noopener">Preview</a>
        <?php echo row_btn('duplicate', (int) $r['id'], 'Duplicate', 'btn-ghost'); ?>
        <?php
        if ($r['status'] === 'published') {
            echo row_btn('toggle', (int) $r['id'], 'Unpublish', 'btn-secondary');
        } else {
            echo row_btn('toggle', (int) $r['id'], 'Publish', 'btn-secondary');
        }
        echo row_btn('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Delete this video permanently?');
        ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="videos.php<?php echo esc(vfilter_qs(['p' => $page - 1])); ?>">&laquo; Prev</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
    <?php if ($i === $page): ?>
      <span class="cur"><?php echo $i; ?></span>
    <?php else: ?>
      <a href="videos.php<?php echo esc(vfilter_qs(['p' => $i])); ?>"><?php echo $i; ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a href="videos.php<?php echo esc(vfilter_qs(['p' => $page + 1])); ?>">Next &raquo;</a>
  <?php endif; ?>
  <span class="total"><?php echo $total; ?> video(s)</span>
</div>
<?php else: ?>
<p class="muted"><?php echo $total; ?> video(s)</p>
<?php endif; ?>

<script>
var ca = document.getElementById('check-all');
if (ca) ca.addEventListener('change', function () {
  var on = this.checked;
  document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) { cb.checked = on; });
});
</script>

<?php admin_foot(); ?>
