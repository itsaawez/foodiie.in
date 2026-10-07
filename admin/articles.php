<?php
/** FOODIIE admin — article list with filters, bulk actions, pagination. */
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
        $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('error', 'Article not found.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM articles WHERE id = :id')->execute([':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Article deleted. ' . $msg);
        } elseif ($action === 'toggle') {
            $new = $row['status'] === 'published' ? 'draft' : 'published';
            $pdo->prepare('UPDATE articles SET status = :s, updated_at = :u WHERE id = :id')
                ->execute([':s' => $new, ':u' => now_utc(), ':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', ($new === 'published' ? 'Article published. ' : 'Article unpublished. ') . $msg);
        } else { // duplicate
            $now = now_utc();
            $ins = $pdo->prepare('INSERT INTO articles(title, slug, description, body, image, image_alt, author_id, category_id, article_type, tags, status, publish_at, created_at, updated_at, reading_time, seo_title, seo_description, canonical, og_image)
                VALUES(:title, :slug, :description, :body, :image, :image_alt, :author_id, :category_id, :at, :tags, :status, NULL, :c, :u, :rt, :seo_title, :seo_description, :canonical, :og_image)');
            $ins->execute([
                ':title' => $row['title'] . ' (Copy)',
                ':slug' => unique_slug($pdo, 'articles', $row['slug'] . '-copy'),
                ':description' => $row['description'],
                ':body' => $row['body'],
                ':image' => $row['image'],
                ':image_alt' => $row['image_alt'],
                ':author_id' => $row['author_id'],
                ':category_id' => $row['category_id'],
                ':at' => $row['article_type'] ?? 'article',
                ':tags' => $row['tags'],
                ':status' => 'draft',
                ':c' => $now, ':u' => $now,
                ':rt' => $row['reading_time'],
                ':seo_title' => $row['seo_title'],
                ':seo_description' => $row['seo_description'],
                ':canonical' => $row['canonical'],
                ':og_image' => $row['og_image'],
            ]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Article duplicated as draft. ' . $msg);
        }
        redirect('articles.php' . $qs);
    }

    if ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $bulk = $_POST['bulk_action'] ?? '';
        if ($ids && in_array($bulk, ['publish', 'unpublish', 'delete'], true)) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulk === 'delete') {
                $pdo->prepare("DELETE FROM articles WHERE id IN ($marks)")->execute($ids);
            } else {
                $st = $bulk === 'publish' ? 'published' : 'draft';
                $pdo->prepare("UPDATE articles SET status = ?, updated_at = ? WHERE id IN ($marks)")
                    ->execute(array_merge([$st, now_utc()], $ids));
            }
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Bulk ' . $bulk . ' applied to ' . count($ids) . ' article(s). ' . $msg);
        } else {
            flash('warning', 'Nothing selected, or unknown bulk action.');
        }
        redirect('articles.php' . $qs);
    }
}

// ---------- filters + pagination ----------
$q = trim($_GET['q'] ?? '');
$fstatus = $_GET['status'] ?? '';
$fcat = (int) ($_GET['cat'] ?? 0);
$ftype = trim($_GET['type'] ?? '');
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(a.title LIKE :q OR a.description LIKE :q OR a.tags LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$valid_status = ['draft', 'review', 'scheduled', 'published', 'archived'];
if (in_array($fstatus, $valid_status, true)) {
    $where[] = 'a.status = :st';
    $params[':st'] = $fstatus;
}
if ($fcat > 0) {
    $where[] = 'a.category_id = :cat';
    $params[':cat'] = $fcat;
}
$valid_types = ['article', 'food-news', 'kitchen-hack', 'health', 'food-fact', 'food-tip', 'trending'];
if (in_array($ftype, $valid_types, true)) {
    $where[] = 'a.article_type = :atype';
    $params[':atype'] = $ftype;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM articles a' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare('SELECT a.*, c.name AS category_name, u.name AS author_name
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN users u ON u.id = a.author_id'
    . $whereSql . ' ORDER BY a.updated_at DESC LIMIT :lim OFFSET :off');
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$cats = $pdo->query('SELECT id, name FROM categories ORDER BY sort, name')->fetchAll();

function filter_qs(array $over = []): string
{
    $p = ['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? '', 'cat' => $_GET['cat'] ?? '', 'type' => $_GET['type'] ?? ''];
    foreach ($over as $k => $v) {
        $p[$k] = $v;
    }
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return $p ? '?' . http_build_query($p) : '';
}

admin_head('Articles', 'articles', $current_user);
?>

<form method="get" action="articles.php" class="filters">
  <div class="field">
    <label for="fq">Search</label>
    <input type="text" id="fq" name="q" value="<?php echo esc($q); ?>" placeholder="Title, excerpt, tags…">
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
    <label for="ftype">Type</label>
    <select id="ftype" name="type">
      <option value="">All types</option>
      <?php echo article_type_options($ftype); ?>
    </select>
  </div>
  <div class="field">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($q !== '' || $fstatus !== '' || $fcat > 0 || $ftype !== ''): ?>
      <a class="btn btn-ghost" href="articles.php">Clear</a>
    <?php endif; ?>
  </div>
</form>

<form id="bulkform" method="post" action="articles.php<?php echo filter_qs(); ?>">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="bulk">
  <div class="toolbar">
    <select name="bulk_action" aria-label="Bulk action">
      <option value="">Bulk actions…</option>
      <option value="publish">Publish</option>
      <option value="unpublish">Unpublish</option>
      <option value="delete">Delete</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Apply this bulk action to the selected articles?')">Apply</button>
    <span class="spacer"></span>
    <a class="btn btn-primary" href="article-edit.php">+ New Article</a>
  </div>
</form>

<div class="table-wrap">
<table class="tbl">
  <thead>
    <tr>
      <th><input type="checkbox" id="check-all" aria-label="Select all"></th>
      <th>Title</th><th>Type</th><th>Category</th><th>Author</th><th>Status</th><th>Published</th><th>Updated</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="9" class="muted">No articles found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><input type="checkbox" name="ids[]" value="<?php echo (int) $r['id']; ?>" form="bulkform"></td>
      <td class="row-title"><a href="article-edit.php?id=<?php echo (int) $r['id']; ?>"><?php echo esc($r['title']); ?></a></td>
      <td><span class="badge b-style"><?php echo esc(str_replace('-', ' ', (string) ($r['article_type'] ?? 'article'))); ?></span></td>
      <td class="muted"><?php echo esc($r['category_name'] ?? '—'); ?></td>
      <td class="muted"><?php echo esc($r['author_name'] ?? '—'); ?></td>
      <td><?php echo status_badge((string) $r['status']); ?></td>
      <td class="muted"><?php echo $r['publish_at'] ? esc(fmt_date($r['publish_at'])) : '—'; ?></td>
      <td class="muted"><?php echo esc(fmt_date($r['updated_at'])); ?></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="article-edit.php?id=<?php echo (int) $r['id']; ?>">Edit</a>
        <a class="btn btn-ghost btn-sm" href="preview.php?type=article&id=<?php echo (int) $r['id']; ?>" target="_blank" rel="noopener">Preview</a>
        <?php echo row_btn('duplicate', (int) $r['id'], 'Duplicate', 'btn-ghost'); ?>
        <?php
        if ($r['status'] === 'published') {
            echo row_btn('toggle', (int) $r['id'], 'Unpublish', 'btn-secondary');
        } else {
            echo row_btn('toggle', (int) $r['id'], 'Publish', 'btn-secondary');
        }
        echo row_btn('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Delete this article permanently?');
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
    <a href="articles.php<?php echo esc(filter_qs(['p' => $page - 1])); ?>">&laquo; Prev</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
    <?php if ($i === $page): ?>
      <span class="cur"><?php echo $i; ?></span>
    <?php else: ?>
      <a href="articles.php<?php echo esc(filter_qs(['p' => $i])); ?>"><?php echo $i; ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a href="articles.php<?php echo esc(filter_qs(['p' => $page + 1])); ?>">Next &raquo;</a>
  <?php endif; ?>
  <span class="total"><?php echo $total; ?> article(s)</span>
</div>
<?php else: ?>
<p class="muted"><?php echo $total; ?> article(s)</p>
<?php endif; ?>

<script>
var ca = document.getElementById('check-all');
if (ca) ca.addEventListener('change', function () {
  var on = this.checked;
  document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) { cb.checked = on; });
});
</script>

<?php admin_foot(); ?>
