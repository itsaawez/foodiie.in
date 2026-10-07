<?php
/**
 * FOODIIE admin — YouTube Shorts list with filters, bulk actions, pagination.
 */
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
        $stmt = $pdo->prepare('SELECT * FROM shorts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('error', 'Short not found.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM shorts WHERE id = :id')->execute([':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Short deleted. ' . $msg);
        } elseif ($action === 'toggle') {
            $new = $row['status'] === 'published' ? 'draft' : 'published';
            $pdo->prepare('UPDATE shorts SET status = :s, updated_at = :u WHERE id = :id')
                ->execute([':s' => $new, ':u' => now_utc(), ':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', ($new === 'published' ? 'Short published. ' : 'Short unpublished. ') . $msg);
        } else { // duplicate
            $now = now_utc();
            $pdo->prepare('INSERT INTO shorts(title, slug, youtube_url, video_id, thumbnail, description, tags,
                status, publish_at, created_at, updated_at)
                VALUES(:title, :slug, :youtube_url, :video_id, :thumbnail, :description, :tags,
                :status, NULL, :c, :u)')->execute([
                ':title' => $row['title'] . ' (Copy)',
                ':slug' => unique_slug($pdo, 'shorts', $row['slug'] . '-copy'),
                ':youtube_url' => $row['youtube_url'],
                ':video_id' => $row['video_id'],
                ':thumbnail' => $row['thumbnail'],
                ':description' => $row['description'],
                ':tags' => $row['tags'],
                ':status' => 'draft',
                ':c' => $now,
                ':u' => $now,
            ]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Short duplicated as draft. ' . $msg);
        }
        redirect('shorts.php' . $qs);
    }

    if ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $bulk = $_POST['bulk_action'] ?? '';
        if ($ids && in_array($bulk, ['publish', 'unpublish', 'delete'], true)) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulk === 'delete') {
                $pdo->prepare("DELETE FROM shorts WHERE id IN ($marks)")->execute($ids);
            } else {
                $st = $bulk === 'publish' ? 'published' : 'draft';
                $pdo->prepare("UPDATE shorts SET status = ?, updated_at = ? WHERE id IN ($marks)")
                    ->execute(array_merge([$st, now_utc()], $ids));
            }
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Bulk ' . $bulk . ' applied to ' . count($ids) . ' short(s). ' . $msg);
        } else {
            flash('warning', 'Nothing selected, or unknown bulk action.');
        }
        redirect('shorts.php' . $qs);
    }
}

// ---------- filters + pagination ----------
$q = trim($_GET['q'] ?? '');
$fstatus = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(s.title LIKE :q OR s.description LIKE :q OR s.tags LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$valid_status = ['draft', 'review', 'scheduled', 'published', 'archived'];
if (in_array($fstatus, $valid_status, true)) {
    $where[] = 's.status = :st';
    $params[':st'] = $fstatus;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM shorts s' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare('SELECT s.* FROM shorts s' . $whereSql . ' ORDER BY s.updated_at DESC LIMIT :lim OFFSET :off');
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

function filter_qs(array $over = []): string
{
    $p = ['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? ''];
    foreach ($over as $k => $v) {
        $p[$k] = $v;
    }
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return $p ? '?' . http_build_query($p) : '';
}

admin_head('Shorts', 'shorts', $current_user);
?>

<form method="get" action="shorts.php" class="filters">
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
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($q !== '' || $fstatus !== ''): ?>
      <a class="btn btn-ghost" href="shorts.php">Clear</a>
    <?php endif; ?>
  </div>
</form>

<form id="bulkform" method="post" action="shorts.php<?php echo filter_qs(); ?>">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="bulk">
  <div class="toolbar">
    <select name="bulk_action" aria-label="Bulk action">
      <option value="">Bulk actions…</option>
      <option value="publish">Publish</option>
      <option value="unpublish">Unpublish</option>
      <option value="delete">Delete</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Apply this bulk action to selected shorts?')">Apply</button>
    <span class="spacer"></span>
    <a class="btn btn-primary" href="short-edit.php">+ Add Short</a>
  </div>
</form>

<div class="table-wrap">
<table class="tbl">
  <thead>
    <tr>
      <th><input type="checkbox" id="check-all" aria-label="Select all"></th>
      <th>Thumb</th>
      <th>Title</th>
      <th>Video ID</th>
      <th>Status</th>
      <th>Published</th>
      <th>Updated</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="8" class="muted">No shorts found. <a href="short-edit.php">Add your first YouTube Short</a>.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <?php
      $thumb = $r['thumbnail'] ?: ($r['video_id'] ? "https://img.youtube.com/vi/{$r['video_id']}/hqdefault.jpg" : '');
    ?>
    <tr>
      <td><input type="checkbox" name="ids[]" value="<?php echo (int) $r['id']; ?>" form="bulkform"></td>
      <td>
        <?php if ($thumb): ?>
          <img src="<?php echo esc($thumb); ?>" alt="" class="short-thumb">
        <?php else: ?>
          <div class="short-thumb" style="display:flex;align-items:center;justify-content:center;color:#6b7280;font-size:10px;">No pic</div>
        <?php endif; ?>
      </td>
      <td class="row-title">
        <a href="short-edit.php?id=<?php echo (int) $r['id']; ?>"><?php echo esc($r['title']); ?></a>
      </td>
      <td class="muted">
        <?php if ($r['video_id']): ?>
          <code><?php echo esc($r['video_id']); ?></code>
          <a href="https://www.youtube.com/shorts/<?php echo urlencode($r['video_id']); ?>" target="_blank" rel="noopener" style="margin-left:4px;" title="Watch on YouTube">&#8599;</a>
        <?php else: ?>
          &mdash;
        <?php endif; ?>
      </td>
      <td><?php echo status_badge((string) $r['status']); ?></td>
      <td class="muted"><?php echo $r['publish_at'] ? esc(fmt_date($r['publish_at'])) : '&mdash;'; ?></td>
      <td class="muted"><?php echo esc(fmt_date($r['updated_at'])); ?></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="short-edit.php?id=<?php echo (int) $r['id']; ?>">Edit</a>
        <?php echo row_btn('duplicate', (int) $r['id'], 'Duplicate', 'btn-ghost'); ?>
        <?php
        if ($r['status'] === 'published') {
            echo row_btn('toggle', (int) $r['id'], 'Unpublish', 'btn-secondary');
        } else {
            echo row_btn('toggle', (int) $r['id'], 'Publish', 'btn-secondary');
        }
        echo row_btn('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Delete this short permanently?');
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
    <a href="shorts.php<?php echo esc(filter_qs(['p' => $page - 1])); ?>">&laquo; Prev</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
    <?php if ($i === $page): ?>
      <span class="cur"><?php echo $i; ?></span>
    <?php else: ?>
      <a href="shorts.php<?php echo esc(filter_qs(['p' => $i])); ?>"><?php echo $i; ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a href="shorts.php<?php echo esc(filter_qs(['p' => $page + 1])); ?>">Next &raquo;</a>
  <?php endif; ?>
  <span class="total"><?php echo $total; ?> shorts</span>
</div>
<?php endif; ?>

<script>
document.getElementById('check-all')?.addEventListener('change', function () {
  document.querySelectorAll('input[form="bulkform"]').forEach(cb => cb.checked = this.checked);
});
</script>

<?php admin_foot(); ?>
