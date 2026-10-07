<?php
/**
 * FOODIIE admin — hierarchical recipe categories manager.
 * Manage cuisines, courses, diet types, ingredients, styles, desserts, etc.
 */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../cms/functions/media.php';

$pdo = db();

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $cat = rc_by_id($id);
        if (!$cat) {
            flash('error', 'Category not found.');
        } else {
            // Check count of linked recipes
            $count = count(rc_recipe_ids($id, false));
            rc_delete($id);
            [$ok, $msg] = regen_site();
            flash($ok ? 'success' : 'warning', "Recipe category '{$cat['name']}' deleted. ({$count} recipe link(s) removed). " . $msg);
        }
        redirect('recipe-categories.php' . (!empty($_POST['type']) ? '?type=' . urlencode($_POST['type']) : ''));
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id = $action === 'update' ? (int) ($_POST['id'] ?? 0) : 0;
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $type = trim($_POST['type'] ?? 'category');
        $parent_id = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $seo_title = trim($_POST['seo_title'] ?? '');
        $seo_description = trim($_POST['seo_description'] ?? '');
        $sort = (int) ($_POST['sort'] ?? 0);

        if ($name === '') {
            flash('error', 'Name is required.');
            redirect('recipe-categories.php' . ($action === 'update' && $id > 0 ? '?edit=' . $id : ''));
        }
        if ($slug === '') {
            $slug = slugify($name);
        }
        $slug = unique_slug($pdo, 'recipe_categories', $slug, $id > 0 ? $id : null);

        // Prevent circular parenting
        if ($id > 0 && $parent_id === $id) {
            $parent_id = null;
        }

        $data = [
            'name'            => $name,
            'slug'            => $slug,
            'type'            => $type,
            'parent_id'       => $parent_id,
            'description'     => $description,
            'image'           => $image,
            'seo_title'       => $seo_title,
            'seo_description' => $seo_description,
            'sort'            => $sort,
        ];

        if ($action === 'create') {
            rc_insert($data);
            flash('success', 'Recipe category created.');
        } else {
            rc_update($id, $data);
            flash('success', 'Recipe category updated.');
        }

        [$ok, $msg] = regen_site();
        if (!$ok) {
            flash('warning', $msg);
        }
        redirect('recipe-categories.php' . ($type ? '?type=' . urlencode($type) : ''));
    }
}

// ---------- data & filters ----------
$active_type = trim($_GET['type'] ?? '');
$types = ['all' => 'All Types', 'cuisine' => 'Cuisines', 'course' => 'Courses', 'diet' => 'Diets', 'ingredient' => 'Ingredients', 'style' => 'Styles', 'dessert' => 'Desserts', 'occasion' => 'Occasions'];

$filter_type = ($active_type !== '' && $active_type !== 'all' && isset($types[$active_type])) ? $active_type : null;

// Get hierarchical flat tree for display
$categories = rc_flat_tree($filter_type);

// Recipe counts per category
$stmt = $pdo->query('SELECT category_id, COUNT(*) AS cnt FROM recipe_category_map GROUP BY category_id');
$recipe_counts = [];
foreach ($stmt->fetchAll() as $r) {
    $recipe_counts[(int) $r['category_id']] = (int) $r['cnt'];
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = rc_by_id((int) $_GET['edit']);
}

admin_head('Recipe Categories', 'recipe-categories', $current_user);
?>

<div class="tabs">
  <?php foreach ($types as $key => $label): ?>
    <a href="recipe-categories.php<?php echo $key === 'all' ? '' : '?type=' . urlencode($key); ?>"
       class="tab-btn <?php echo ($active_type === $key || ($active_type === '' && $key === 'all')) ? 'active' : ''; ?>">
      <?php echo esc($label); ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="edit-layout">
  <div>
    <div class="panel">
      <h2>All Recipe Categories <?php echo $filter_type ? '(' . esc($types[$filter_type] ?? $filter_type) . ')' : '(' . count($categories) . ')'; ?></h2>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Slug</th>
              <th>Recipes</th>
              <th>Sort</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($categories)): ?>
            <tr><td colspan="6" class="muted">No categories found.</td></tr>
          <?php endif; ?>
          <?php foreach ($categories as $c): ?>
            <?php
              $depth = (int) ($c['depth'] ?? 0);
              $indent = str_repeat('&mdash; ', $depth);
              $c_id = (int) $c['id'];
              $type_cls = 'b-' . preg_replace('/[^a-z0-9_-]/', '', strtolower($c['type']));
            ?>
            <tr>
              <td class="row-title">
                <?php echo $indent; ?>
                <a href="recipe-categories.php?edit=<?php echo $c_id . ($filter_type ? '&type=' . urlencode($filter_type) : ''); ?>">
                  <?php echo esc($c['name']); ?>
                </a>
              </td>
              <td><span class="badge <?php echo esc($type_cls); ?>"><?php echo esc($c['type']); ?></span></td>
              <td class="muted"><?php echo esc($c['slug']); ?></td>
              <td><?php echo $recipe_counts[$c_id] ?? 0; ?></td>
              <td><?php echo (int) $c['sort']; ?></td>
              <td class="actions">
                <a class="btn btn-secondary btn-sm" href="recipe-categories.php?edit=<?php echo $c_id . ($filter_type ? '&type=' . urlencode($filter_type) : ''); ?>">Edit</a>
                <form class="inline-form" method="post" action="recipe-categories.php" onsubmit="return confirm('Delete this category? Sub-categories will be moved to top level.');">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo $c_id; ?>">
                  <?php if ($filter_type): ?>
                    <input type="hidden" name="type" value="<?php echo esc($filter_type); ?>">
                  <?php endif; ?>
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="panel">
      <h2><?php echo $edit ? 'Edit Recipe Category' : 'Add Recipe Category'; ?></h2>
      <form method="post" action="recipe-categories.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
        <?php if ($edit): ?>
          <input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>">
        <?php endif; ?>

        <div class="field">
          <label for="rc-name">Name *</label>
          <input type="text" id="rc-name" name="name" value="<?php echo esc($edit['name'] ?? ''); ?>" required placeholder="e.g. North Indian">
        </div>

        <div class="field">
          <label for="rc-slug">Slug</label>
          <input type="text" id="rc-slug" name="slug" value="<?php echo esc($edit['slug'] ?? ''); ?>" placeholder="Auto-generated if empty">
          <div class="hint">URL slug, e.g. north-indian</div>
        </div>

        <div class="field">
          <label for="rc-type">Category Type *</label>
          <select id="rc-type" name="type" required>
            <?php
              $available_types = [
                'cuisine'    => 'Cuisine (e.g. North Indian, Italian)',
                'course'     => 'Course (e.g. Breakfast, Dinner, Snacks)',
                'diet'       => 'Diet (e.g. Vegetarian, Keto, Vegan)',
                'ingredient' => 'By Ingredient (e.g. Paneer, Chicken, Biryani)',
                'style'      => 'Style (e.g. Street Food, One-Pot, Tandoor)',
                'dessert'    => 'Dessert (e.g. Cakes, Regional Sweets)',
                'occasion'   => 'Occasion (e.g. Festive, Party, Brunch)',
                'category'   => 'General / Other',
              ];
              $cur_type = $edit['type'] ?? ($filter_type ?: 'cuisine');
              foreach ($available_types as $k => $lbl) {
                  $sel = ($cur_type === $k) ? ' selected' : '';
                  echo "<option value=\"{$k}\"{$sel}>" . esc($lbl) . "</option>";
              }
            ?>
          </select>
        </div>

        <div class="field">
          <label for="rc-parent">Parent Category (optional)</label>
          <select id="rc-parent" name="parent_id">
            <?php echo rc_options(null, $edit['parent_id'] ?? null, $edit ? (int) $edit['id'] : null); ?>
          </select>
          <div class="hint">Organize hierarchically (e.g. Punjabi inside North Indian).</div>
        </div>

        <div class="field">
          <label for="rc-desc">Description</label>
          <textarea id="rc-desc" name="description" rows="3"><?php echo esc($edit['description'] ?? ''); ?></textarea>
        </div>

        <div class="field">
          <label for="rc-image">Image</label>
          <select id="rc-image" name="image"><?php echo media_options($pdo, (string) ($edit['image'] ?? '')); ?></select>
        </div>

        <div class="field">
          <label for="rc-sort">Sort Order</label>
          <input type="number" id="rc-sort" name="sort" value="<?php echo (int) ($edit['sort'] ?? 0); ?>">
        </div>

        <div class="field">
          <label for="rc-seo-title">SEO Title</label>
          <input type="text" id="rc-seo-title" name="seo_title" value="<?php echo esc($edit['seo_title'] ?? ''); ?>">
        </div>

        <div class="field">
          <label for="rc-seo-desc">SEO Description</label>
          <textarea id="rc-seo-desc" name="seo_description" rows="2"><?php echo esc($edit['seo_description'] ?? ''); ?></textarea>
        </div>

        <div class="toolbar">
          <button type="submit" class="btn btn-primary"><?php echo $edit ? 'Save Changes' : 'Add Category'; ?></button>
          <?php if ($edit): ?>
            <a class="btn btn-ghost" href="recipe-categories.php<?php echo $filter_type ? '?type=' . urlencode($filter_type) : ''; ?>">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php admin_foot(); ?>
