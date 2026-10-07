<?php
/** FOODIIE admin — recipe list with filters, bulk actions, pagination. */
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
        $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('error', 'Recipe not found.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM recipes WHERE id = :id')->execute([':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Recipe deleted. ' . $msg);
        } elseif ($action === 'toggle') {
            $new = $row['status'] === 'published' ? 'draft' : 'published';
            $pdo->prepare('UPDATE recipes SET status = :s, updated_at = :u WHERE id = :id')
                ->execute([':s' => $new, ':u' => now_utc(), ':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', ($new === 'published' ? 'Recipe published. ' : 'Recipe unpublished. ') . $msg);
        } else { // duplicate
            $now = now_utc();
            $ins = $pdo->prepare('INSERT INTO recipes(name, slug, description, image, image_alt, category_id, course, diet_type,
                cooking_method, skill_level, prep_time, cook_time, total_time,
                servings, difficulty, cuisine, ingredients, instructions, calories, protein, carbs, fat, tags, status,
                publish_at, created_at, updated_at, seo_title, seo_description, canonical, og_image)
                VALUES(:name, :slug, :description, :image, :image_alt, :category_id, :course, :diet_type,
                :cooking_method, :skill_level, :prep_time, :cook_time, :total_time,
                :servings, :difficulty, :cuisine, :ingredients, :instructions, :calories, :protein, :carbs, :fat, :tags, :status,
                NULL, :c, :u, :seo_title, :seo_description, :canonical, :og_image)');
            $ins->execute([
                ':name' => $row['name'] . ' (Copy)',
                ':slug' => unique_slug($pdo, 'recipes', $row['slug'] . '-copy'),
                ':description' => $row['description'], ':image' => $row['image'], ':image_alt' => $row['image_alt'],
                ':category_id' => $row['category_id'] ?? null,
                ':course' => $row['course'] ?? '', ':diet_type' => $row['diet_type'] ?? '',
                ':cooking_method' => $row['cooking_method'] ?? '', ':skill_level' => $row['skill_level'] ?? '',
                ':prep_time' => $row['prep_time'], ':cook_time' => $row['cook_time'], ':total_time' => $row['total_time'],
                ':servings' => $row['servings'], ':difficulty' => $row['difficulty'], ':cuisine' => $row['cuisine'],
                ':ingredients' => $row['ingredients'], ':instructions' => $row['instructions'],
                ':calories' => $row['calories'], ':protein' => $row['protein'], ':carbs' => $row['carbs'], ':fat' => $row['fat'],
                ':tags' => $row['tags'], ':status' => 'draft', ':c' => $now, ':u' => $now,
                ':seo_title' => $row['seo_title'], ':seo_description' => $row['seo_description'],
                ':canonical' => $row['canonical'] ?? '', ':og_image' => $row['og_image'] ?? '',
            ]);
            $new_id = (int) $pdo->lastInsertId();
            $old_cats = rc_for_recipe($id);
            if ($old_cats) {
                rc_set_recipe($new_id, $old_cats);
            }
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Recipe duplicated as draft. ' . $msg);
        }
        redirect('recipes.php' . $qs);
    }

    if ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $bulk = $_POST['bulk_action'] ?? '';
        if ($ids && in_array($bulk, ['publish', 'unpublish', 'delete'], true)) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            if ($bulk === 'delete') {
                $pdo->prepare("DELETE FROM recipes WHERE id IN ($marks)")->execute($ids);
                $pdo->prepare("DELETE FROM recipe_category_map WHERE recipe_id IN ($marks)")->execute($ids);
            } else {
                $st = $bulk === 'publish' ? 'published' : 'draft';
                $pdo->prepare("UPDATE recipes SET status = ?, updated_at = ? WHERE id IN ($marks)")
                    ->execute(array_merge([$st, now_utc()], $ids));
            }
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Bulk ' . $bulk . ' applied to ' . count($ids) . ' recipe(s). ' . $msg);
        } else {
            flash('warning', 'Nothing selected, or unknown bulk action.');
        }
        redirect('recipes.php' . $qs);
    }
}

// ---------- filters + pagination ----------
$q = trim($_GET['q'] ?? '');
$fstatus = $_GET['status'] ?? '';
$fcourse = trim($_GET['course'] ?? '');
$fdiet = trim($_GET['diet'] ?? '');
$fvideo = trim($_GET['video'] ?? '');
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE :q OR description LIKE :q OR tags LIKE :q OR cuisine LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$valid_status = ['draft', 'review', 'scheduled', 'published', 'archived'];
if (in_array($fstatus, $valid_status, true)) {
    $where[] = 'status = :st';
    $params[':st'] = $fstatus;
}
if ($fcourse !== '') {
    $where[] = 'course = :fcourse';
    $params[':fcourse'] = $fcourse;
}
if ($fdiet !== '') {
    $where[] = 'diet_type = :fdiet';
    $params[':fdiet'] = $fdiet;
}
if ($fvideo === 'with') {
    $where[] = "(youtube_video_id != '' AND youtube_enabled = 1)";
} elseif ($fvideo === 'without') {
    $where[] = "(youtube_video_id = '' OR youtube_video_id IS NULL OR youtube_enabled = 0)";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM recipes' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $per;

$stmt = $pdo->prepare('SELECT * FROM recipes' . $whereSql . ' ORDER BY updated_at DESC LIMIT :lim OFFSET :off');
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

function rfilter_qs(array $over = []): string
{
    $p = ['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? '', 'course' => $_GET['course'] ?? '', 'diet' => $_GET['diet'] ?? '', 'video' => $_GET['video'] ?? ''];
    foreach ($over as $k => $v) {
        $p[$k] = $v;
    }
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return $p ? '?' . http_build_query($p) : '';
}

admin_head('Recipes', 'recipes', $current_user);
?>

<form method="get" action="recipes.php" class="filters">
  <div class="field">
    <label for="fq">Search</label>
    <input type="text" id="fq" name="q" value="<?php echo esc($q); ?>" placeholder="Name, cuisine, tags…">
  </div>
  <div class="field">
    <label for="fstatus">Status</label>
    <select id="fstatus" name="status">
      <option value="">All statuses</option>
      <?php echo status_options($fstatus); ?>
    </select>
  </div>
  <div class="field">
    <label for="fcourse">Course</label>
    <select id="fcourse" name="course">
      <option value="">All courses</option>
      <?php echo course_options($fcourse); ?>
    </select>
  </div>
  <div class="field">
    <label for="fdiet">Diet</label>
    <select id="fdiet" name="diet">
      <option value="">All diets</option>
      <?php echo diet_type_options($fdiet); ?>
    </select>
  </div>
  <div class="field">
    <label for="fvideo">Video</label>
    <select id="fvideo" name="video">
      <option value="">All recipes</option>
      <option value="with" <?php echo $fvideo === 'with' ? 'selected' : ''; ?>>With Video</option>
      <option value="without" <?php echo $fvideo === 'without' ? 'selected' : ''; ?>>Without Video</option>
    </select>
  </div>
  <div class="field">
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($q !== '' || $fstatus !== '' || $fcourse !== '' || $fdiet !== '' || $fvideo !== ''): ?>
      <a class="btn btn-ghost" href="recipes.php">Clear</a>
    <?php endif; ?>
  </div>
</form>

<form id="bulkform" method="post" action="recipes.php<?php echo rfilter_qs(); ?>">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="bulk">
  <div class="toolbar">
    <select name="bulk_action" aria-label="Bulk action">
      <option value="">Bulk actions…</option>
      <option value="publish">Publish</option>
      <option value="unpublish">Unpublish</option>
      <option value="delete">Delete</option>
    </select>
    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Apply this bulk action to the selected recipes?')">Apply</button>
    <span class="spacer"></span>
    <a class="btn btn-primary" href="recipe-edit.php">+ New Recipe</a>
  </div>
</form>

<div class="table-wrap">
<table class="tbl">
  <thead>
    <tr>
      <th><input type="checkbox" id="check-all" aria-label="Select all"></th>
      <th>Name</th><th>Cuisine</th><th>Course</th><th>Diet</th><th>Difficulty</th><th>Video</th><th>Status</th><th>Published</th><th>Updated</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="11" class="muted">No recipes found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><input type="checkbox" name="ids[]" value="<?php echo (int) $r['id']; ?>" form="bulkform"></td>
      <td class="row-title"><a href="recipe-edit.php?id=<?php echo (int) $r['id']; ?>"><?php echo esc($r['name']); ?></a></td>
      <td class="muted"><?php echo esc($r['cuisine'] ?: '—'); ?></td>
      <td>
        <?php if (!empty($r['course'])): ?>
          <span class="badge b-course"><?php echo esc($r['course']); ?></span>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if (!empty($r['diet_type'])): ?>
          <span class="badge b-diet"><?php echo esc($r['diet_type']); ?></span>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </td>
      <td class="muted"><?php echo esc($r['difficulty'] ?: '—'); ?></td>
      <td>
        <?php if (!empty($r['youtube_video_id']) && (string)($r['youtube_enabled'] ?? '1') === '1'): ?>
          <span class="badge" style="background:#fff3cd;color:#856404;border:1px solid #ffeeba;font-weight:600;display:inline-flex;align-items:center;gap:4px;" title="<?php echo esc($r['youtube_video_title'] ?: 'YouTube Video'); ?>">
            <span style="color:#e50914;">▶</span> YouTube
          </span>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </td>
      <td><?php echo status_badge((string) $r['status']); ?></td>
      <td class="muted"><?php echo $r['publish_at'] ? esc(fmt_date($r['publish_at'])) : '—'; ?></td>
      <td class="muted"><?php echo esc(fmt_date($r['updated_at'])); ?></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="recipe-edit.php?id=<?php echo (int) $r['id']; ?>">Edit</a>
        <a class="btn btn-ghost btn-sm" href="preview.php?type=recipe&id=<?php echo (int) $r['id']; ?>" target="_blank" rel="noopener">Preview</a>
        <?php echo row_btn('duplicate', (int) $r['id'], 'Duplicate', 'btn-ghost'); ?>
        <?php
        if ($r['status'] === 'published') {
            echo row_btn('toggle', (int) $r['id'], 'Unpublish', 'btn-secondary');
        } else {
            echo row_btn('toggle', (int) $r['id'], 'Publish', 'btn-secondary');
        }
        echo row_btn('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Delete this recipe permanently?');
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
    <a href="recipes.php<?php echo esc(rfilter_qs(['p' => $page - 1])); ?>">&laquo; Prev</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
    <?php if ($i === $page): ?>
      <span class="cur"><?php echo $i; ?></span>
    <?php else: ?>
      <a href="recipes.php<?php echo esc(rfilter_qs(['p' => $i])); ?>"><?php echo $i; ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a href="recipes.php<?php echo esc(rfilter_qs(['p' => $page + 1])); ?>">Next &raquo;</a>
  <?php endif; ?>
  <span class="total"><?php echo $total; ?> recipe(s)</span>
</div>
<?php else: ?>
<p class="muted"><?php echo $total; ?> recipe(s)</p>
<?php endif; ?>

<script>
var ca = document.getElementById('check-all');
if (ca) ca.addEventListener('change', function () {
  var on = this.checked;
  document.querySelectorAll('input[name="ids[]"]').forEach(function (cb) { cb.checked = on; });
});
</script>

<?php admin_foot(); ?>
