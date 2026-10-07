<?php
/**
 * FOODIIE — hierarchical recipe category helpers.
 *
 * recipe_categories holds the unified taxonomy for recipes:
 * cuisines, courses/meals, diet types, cooking styles, occasions, etc.
 *
 * Each row has a `type` field:
 *   'ingredient'  — Paneer Recipes, Chicken Recipes, …
 *   'cuisine'     — North Indian, Chinese, Italian, …
 *   'course'      — Breakfast, Lunch, Dinner, Snacks, …
 *   'diet'        — Keto, Vegan, Low-Carb, …
 *   'style'       — Street Food, Comfort Food, One-Pot, …
 *   'dessert'     — Cakes, Cookies, Regional Desserts, …
 *   'occasion'    — Diwali, Christmas, Navratri, …
 *
 * Parent/child hierarchy: parent_id is NULL for top-level items.
 */

require_once __DIR__ . '/db.php';

/* ------------------------------------------------------------------ */
/* Queries                                                            */
/* ------------------------------------------------------------------ */

/**
 * All recipe categories, optionally filtered by type.
 * Returns flat array sorted by sort, name.
 */
function rc_all(?string $type = null): array
{
    if ($type !== null) {
        $stmt = db()->prepare('SELECT * FROM recipe_categories WHERE type = :t ORDER BY sort ASC, name ASC');
        $stmt->execute([':t' => $type]);
    } else {
        $stmt = db()->query('SELECT * FROM recipe_categories ORDER BY type ASC, sort ASC, name ASC');
    }
    return $stmt->fetchAll();
}

/** Single category by ID. */
function rc_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM recipe_categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Single category by slug. */
function rc_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM recipe_categories WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Children of a given parent (NULL = top-level). */
function rc_children(?int $parent_id): array
{
    if ($parent_id === null) {
        $stmt = db()->query('SELECT * FROM recipe_categories WHERE parent_id IS NULL ORDER BY sort ASC, name ASC');
    } else {
        $stmt = db()->prepare('SELECT * FROM recipe_categories WHERE parent_id = :pid ORDER BY sort ASC, name ASC');
        $stmt->execute([':pid' => $parent_id]);
    }
    return $stmt->fetchAll();
}

/**
 * Build a nested tree from the flat list.
 * Each node gets a 'children' key with its sub-categories.
 * Optionally filter by type first.
 */
function rc_tree(?string $type = null): array
{
    $all = rc_all($type);
    $byId = [];
    foreach ($all as &$row) {
        $row['children'] = [];
        $byId[(int) $row['id']] = &$row;
    }
    unset($row);

    $tree = [];
    foreach ($byId as &$row) {
        $pid = $row['parent_id'];
        if ($pid === null || !isset($byId[(int) $pid])) {
            $tree[] = &$row;
        } else {
            $byId[(int) $pid]['children'][] = &$row;
        }
    }
    unset($row);
    return $tree;
}

/**
 * Flat list with depth for <select> indentation.
 * Returns [['id' => …, 'name' => …, 'depth' => 0|1|2, …], …]
 */
function rc_flat_tree(?string $type = null): array
{
    $tree = rc_tree($type);
    $flat = [];
    $walk = function (array $nodes, int $depth) use (&$flat, &$walk) {
        foreach ($nodes as $node) {
            $node['depth'] = $depth;
            $children = $node['children'];
            unset($node['children']);
            $flat[] = $node;
            $walk($children, $depth + 1);
        }
    };
    $walk($tree, 0);
    return $flat;
}

/** All distinct types currently used. */
function rc_types(): array
{
    return array_column(
        db()->query("SELECT DISTINCT type FROM recipe_categories ORDER BY type")->fetchAll(),
        'type'
    );
}

/**
 * Full category tree grouped by type — used by the mega menu.
 * Returns ['cuisine' => [tree], 'course' => [tree], …]
 */
function rc_mega_menu(): array
{
    $types = ['ingredient', 'cuisine', 'course', 'diet', 'style', 'dessert', 'occasion'];
    $menu = [];
    foreach ($types as $t) {
        $tree = rc_tree($t);
        if (!empty($tree)) {
            $menu[$t] = $tree;
        }
    }
    return $menu;
}

/** Category map: id => row for all recipe categories. */
function rc_map(): array
{
    $map = [];
    foreach (rc_all() as $row) {
        $map[(int) $row['id']] = $row;
    }
    return $map;
}

/* ------------------------------------------------------------------ */
/* Category ↔ Recipe mapping                                          */
/* ------------------------------------------------------------------ */

/** Get all recipe_category IDs assigned to a recipe. */
function rc_for_recipe(int $recipe_id): array
{
    $stmt = db()->prepare('SELECT category_id FROM recipe_category_map WHERE recipe_id = :rid');
    $stmt->execute([':rid' => $recipe_id]);
    return array_column($stmt->fetchAll(), 'category_id');
}

/** Get all recipe IDs in a category (and optionally its children). */
function rc_recipe_ids(int $category_id, bool $include_children = false): array
{
    $ids = [$category_id];
    if ($include_children) {
        $children = rc_children($category_id);
        foreach ($children as $child) {
            $ids[] = (int) $child['id'];
        }
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT DISTINCT recipe_id FROM recipe_category_map WHERE category_id IN ({$placeholders})");
    $stmt->execute($ids);
    return array_column($stmt->fetchAll(), 'recipe_id');
}

/** Set the recipe_category IDs for a recipe (replaces all existing). */
function rc_set_recipe(int $recipe_id, array $category_ids): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM recipe_category_map WHERE recipe_id = :rid')
        ->execute([':rid' => $recipe_id]);
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO recipe_category_map (recipe_id, category_id) VALUES (:rid, :cid)');
    foreach ($category_ids as $cid) {
        $cid = (int) $cid;
        if ($cid > 0) {
            $stmt->execute([':rid' => $recipe_id, ':cid' => $cid]);
        }
    }
}

/* ------------------------------------------------------------------ */
/* Admin CRUD                                                         */
/* ------------------------------------------------------------------ */

/** Insert a new recipe category. Returns the new ID. */
function rc_insert(array $data): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO recipe_categories (parent_id, name, slug, type, description, image, seo_title, seo_description, sort)
         VALUES (:parent_id, :name, :slug, :type, :description, :image, :seo_title, :seo_description, :sort)'
    );
    $stmt->execute([
        ':parent_id'       => $data['parent_id'] ?: null,
        ':name'            => $data['name'],
        ':slug'            => $data['slug'],
        ':type'            => $data['type'] ?? 'category',
        ':description'     => $data['description'] ?? '',
        ':image'           => $data['image'] ?? '',
        ':seo_title'       => $data['seo_title'] ?? '',
        ':seo_description' => $data['seo_description'] ?? '',
        ':sort'            => (int) ($data['sort'] ?? 0),
    ]);
    return (int) $pdo->lastInsertId();
}

/** Update a recipe category. */
function rc_update(int $id, array $data): void
{
    db()->prepare(
        'UPDATE recipe_categories SET parent_id = :parent_id, name = :name, slug = :slug, type = :type,
         description = :description, image = :image, seo_title = :seo_title,
         seo_description = :seo_description, sort = :sort WHERE id = :id'
    )->execute([
        ':id'              => $id,
        ':parent_id'       => $data['parent_id'] ?: null,
        ':name'            => $data['name'],
        ':slug'            => $data['slug'],
        ':type'            => $data['type'] ?? 'category',
        ':description'     => $data['description'] ?? '',
        ':image'           => $data['image'] ?? '',
        ':seo_title'       => $data['seo_title'] ?? '',
        ':seo_description' => $data['seo_description'] ?? '',
        ':sort'            => (int) ($data['sort'] ?? 0),
    ]);
}

/** Delete a recipe category (children become orphans — parent_id set to NULL). */
function rc_delete(int $id): void
{
    $pdo = db();
    // Re-parent children to NULL (top-level).
    $pdo->prepare('UPDATE recipe_categories SET parent_id = NULL WHERE parent_id = :id')
        ->execute([':id' => $id]);
    // Remove category-recipe mappings.
    $pdo->prepare('DELETE FROM recipe_category_map WHERE category_id = :id')
        ->execute([':id' => $id]);
    // Delete the category itself.
    $pdo->prepare('DELETE FROM recipe_categories WHERE id = :id')
        ->execute([':id' => $id]);
}

/* ------------------------------------------------------------------ */
/* Admin <select> / <option> helpers                                  */
/* ------------------------------------------------------------------ */

/**
 * Recipe category <option> list with indentation.
 * Supports multi-select: $selected can be a single int or an array of ints.
 */
function rc_options(?string $type = null, $selected = null, ?int $exclude_id = null): string
{
    $flat = rc_flat_tree($type);
    $sel = is_array($selected) ? array_map('intval', $selected) : [(int) $selected];
    $out = '<option value="">— None —</option>';
    foreach ($flat as $row) {
        $id = (int) $row['id'];
        if ($exclude_id !== null && $id === $exclude_id) {
            continue;
        }
        $indent = str_repeat('— ', $row['depth']);
        $tag = $row['type'] !== 'category' ? ' [' . $row['type'] . ']' : '';
        $is_sel = in_array($id, $sel, true) ? ' selected' : '';
        $out .= '<option value="' . $id . '"' . $is_sel . '>' . esc($indent . $row['name'] . $tag) . '</option>';
    }
    return $out;
}

/**
 * Checkbox list of recipe categories for recipe editor.
 * Returns HTML string of checkboxes grouped by type.
 */
function rc_checkboxes(string $field_name, array $selected_ids, ?string $type = null): string
{
    $flat = rc_flat_tree($type);
    if (empty($flat)) {
        return '<p class="muted">No categories yet.</p>';
    }
    $html = '<div class="checkbox-grid">';
    foreach ($flat as $row) {
        $id = (int) $row['id'];
        $indent = str_repeat('&nbsp;&nbsp;', $row['depth'] * 2);
        $checked = in_array($id, $selected_ids, true) ? ' checked' : '';
        $html .= '<label class="checkbox-item">'
            . '<input type="checkbox" name="' . esc($field_name) . '[]" value="' . $id . '"' . $checked . '> '
            . $indent . esc($row['name'])
            . '</label>';
    }
    $html .= '</div>';
    return $html;
}
