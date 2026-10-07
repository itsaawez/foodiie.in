<?php
/** FOODIIE admin — categories: list + create/edit. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        // Block delete when articles/videos reference this category.
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE category_id = :id');
        $stmt->execute([':id' => $id]);
        $art = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM videos WHERE category_id = :id');
        $stmt->execute([':id' => $id]);
        $vid = (int) $stmt->fetchColumn();
        if ($art > 0 || $vid > 0) {
            flash('error', "Cannot delete: this category is used by {$art} article(s) and {$vid} video(s). Reassign them first.");
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $id]);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', 'Category deleted. ' . $msg);
        }
        redirect('categories.php');
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $seo_title = trim($_POST['seo_title'] ?? '');
        $seo_description = trim($_POST['seo_description'] ?? '');
        $sort = (int) ($_POST['sort'] ?? 0);

        if ($name === '') {
            flash('error', 'Name is required.');
            redirect('categories.php' . ($action === 'update' && $id > 0 ? '?edit=' . $id : ''));
        }
        if ($slug === '') {
            $slug = slugify($name);
        }
        $slug = unique_slug($pdo, 'categories', $slug, $id > 0 ? $id : null);

        if ($action === 'create') {
            $pdo->prepare('INSERT INTO categories(name, slug, description, image, seo_title, seo_description, sort)
                VALUES(:n, :s, :d, :i, :st, :sd, :sort)')
                ->execute([':n' => $name, ':s' => $slug, ':d' => $description, ':i' => $image,
                    ':st' => $seo_title, ':sd' => $seo_description, ':sort' => $sort]);
            flash('success', 'Category created.');
        } else {
            $pdo->prepare('UPDATE categories SET name=:n, slug=:s, description=:d, image=:i,
                seo_title=:st, seo_description=:sd, sort=:sort WHERE id=:id')
                ->execute([':n' => $name, ':s' => $slug, ':d' => $description, ':i' => $image,
                    ':st' => $seo_title, ':sd' => $seo_description, ':sort' => $sort, ':id' => $id]);
            flash('success', 'Category updated.');
        }
        [$ok, $msg] = regen_site();
        if (!$ok) {
            flash('warning', $msg);
        }
        redirect('categories.php');
    }
}

// ---------- data ----------
$cats = $pdo->query('SELECT c.*,
    (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) AS article_count,
    (SELECT COUNT(*) FROM videos v WHERE v.category_id = c.id) AS video_count
    FROM categories c ORDER BY c.sort, c.name')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}

admin_head('Categories', 'categories', $current_user);
?>

<div class="panel">
  <h2><?php echo $edit ? 'Edit category' : 'Add category'; ?></h2>
  <form method="post" action="categories.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
    <?php if ($edit): ?>
      <input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>">
    <?php endif; ?>
    <div class="form-row">
      <div class="field">
        <label for="c-name">Name *</label>
        <input type="text" id="c-name" name="name" value="<?php echo esc($edit['name'] ?? ''); ?>" required>
      </div>
      <div class="field">
        <label for="c-slug">Slug</label>
        <input type="text" id="c-slug" name="slug" value="<?php echo esc($edit['slug'] ?? ''); ?>">
        <div class="hint">Leave blank to auto-generate.</div>
      </div>
    </div>
    <div class="field">
      <label for="c-desc">Description</label>
      <textarea id="c-desc" name="description"><?php echo esc($edit['description'] ?? ''); ?></textarea>
    </div>
    <div class="form-row">
      <div class="field">
        <label for="c-image">Image</label>
        <select id="c-image" name="image"><?php echo media_options($pdo, (string) ($edit['image'] ?? '')); ?></select>
      </div>
      <div class="field">
        <label for="c-sort">Sort order</label>
        <input type="number" id="c-sort" name="sort" value="<?php echo (int) ($edit['sort'] ?? 0); ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="field">
        <label for="c-seo-t">SEO title</label>
        <input type="text" id="c-seo-t" name="seo_title" value="<?php echo esc($edit['seo_title'] ?? ''); ?>">
      </div>
      <div class="field">
        <label for="c-seo-d">SEO description</label>
        <input type="text" id="c-seo-d" name="seo_description" value="<?php echo esc($edit['seo_description'] ?? ''); ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><?php echo $edit ? 'Save changes' : 'Add category'; ?></button>
    <?php if ($edit): ?>
      <a class="btn btn-ghost" href="categories.php">Cancel</a>
    <?php endif; ?>
  </form>
</div>

<div class="panel">
  <h2>All categories</h2>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Name</th><th>Slug</th><th>Articles</th><th>Videos</th><th>Sort</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$cats): ?>
        <tr><td colspan="6" class="muted">No categories yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($cats as $c): ?>
        <tr>
          <td class="row-title"><?php echo esc($c['name']); ?></td>
          <td class="muted"><?php echo esc($c['slug']); ?></td>
          <td><?php echo (int) $c['article_count']; ?></td>
          <td><?php echo (int) $c['video_count']; ?></td>
          <td class="muted"><?php echo (int) $c['sort']; ?></td>
          <td class="actions">
            <a class="btn btn-ghost btn-sm" href="categories.php?edit=<?php echo (int) $c['id']; ?>">Edit</a>
            <?php echo row_btn('delete', (int) $c['id'], 'Delete', 'btn-danger', 'Delete this category?'); ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php admin_foot(); ?>
