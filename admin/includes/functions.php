<?php
/**
 * FOODIIE admin — shared backend + UI helpers.
 * Loaded by includes/guard.php on every admin page.
 */

/** Flash a one-time notice (shown on next page render). */
function flash(string $type, string $msg): void
{
    start_session();
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}

/** Take + clear queued flash notices. */
function take_flashes(): array
{
    start_session();
    $f = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return is_array($f) ? $f : [];
}

/** Render queued flashes as alert HTML. */
function render_flashes(): string
{
    $out = '';
    foreach (take_flashes() as $f) {
        $t = in_array($f['t'] ?? '', ['success', 'error', 'warning', 'info'], true) ? $f['t'] : 'info';
        $out .= '<div class="alert alert-' . $t . '">' . esc($f['m'] ?? '') . '</div>';
    }
    return $out;
}

/**
 * Regenerate the static site. Returns [ok, message].
 * Safe when the generator has not been built yet.
 */
function regen_site(): array
{
    $gen = dirname(__DIR__, 2) . '/cms/generator/generate.php';
    if (!is_readable($gen)) {
        return [false, 'Site generator not built yet — content saved; static files will regenerate once the generator exists.'];
    }
    require_once $gen;
    if (!function_exists('generate_site')) {
        return [false, 'generate_site() is not defined in the generator.'];
    }
    try {
        $result = generate_site();
        $msg = is_string($result) && $result !== '' ? $result : 'Site regenerated.';
        return [true, $msg];
    } catch (Throwable $e) {
        return [false, 'Regeneration failed: ' . $e->getMessage()];
    }
}

/** Redirect helper (relative admin URLs). */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Status pill. */
function status_badge(string $status): string
{
    $allowed = ['draft', 'review', 'scheduled', 'published', 'archived'];
    $s = in_array($status, $allowed, true) ? $status : 'draft';
    return '<span class="badge b-' . $s . '">' . esc(ucfirst($s)) . '</span>';
}

/** Status <option> list. */
function status_options(string $selected): string
{
    $out = '';
    foreach (['draft' => 'Draft', 'review' => 'In Review', 'scheduled' => 'Scheduled', 'published' => 'Published', 'archived' => 'Archived'] as $v => $label) {
        $out .= '<option value="' . $v . '"' . ($v === $selected ? ' selected' : '') . '>' . $label . '</option>';
    }
    return $out;
}

/** Media <option> list (value = stored filename like uploads/2026/09/x.jpg). */
function media_options(PDO $pdo, string $selected = ''): string
{
    $rows = $pdo->query('SELECT filename, original_name FROM media ORDER BY created_at DESC')->fetchAll();
    $out = '<option value="">— No image —</option>';
    foreach ($rows as $r) {
        $label = $r['original_name'] !== '' ? $r['original_name'] . ' (' . $r['filename'] . ')' : $r['filename'];
        $out .= '<option value="' . esc($r['filename']) . '"' . ($r['filename'] === $selected ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    return $out;
}

/** Users <option> list for author selects. */
function user_options(PDO $pdo, $selected): string
{
    $rows = $pdo->query('SELECT id, name, email FROM users ORDER BY name')->fetchAll();
    $out = '<option value="">— None —</option>';
    foreach ($rows as $r) {
        $out .= '<option value="' . (int) $r['id'] . '"' . ((int) $selected === (int) $r['id'] ? ' selected' : '') . '>' . esc($r['name'] . ' (' . $r['email'] . ')') . '</option>';
    }
    return $out;
}

/** Categories <option> list. */
function category_options(PDO $pdo, $selected): string
{
    $rows = $pdo->query('SELECT id, name FROM categories ORDER BY sort, name')->fetchAll();
    $out = '<option value="">— None —</option>';
    foreach ($rows as $r) {
        $out .= '<option value="' . (int) $r['id'] . '"' . ((int) $selected === (int) $r['id'] ? ' selected' : '') . '>' . esc($r['name']) . '</option>';
    }
    return $out;
}

/** Small POST form rendering a single row-action button (Edit handled as link). */
function row_btn(string $action, int $id, string $label, string $class, string $confirm = ''): string
{
    $c = $confirm !== '' ? ' onsubmit="return confirm(' . htmlspecialchars(json_encode($confirm), ENT_QUOTES, 'UTF-8') . ');"' : '';
    return '<form method="post" class="inline-form"' . $c . '>'
        . csrf_field()
        . '<input type="hidden" name="action" value="' . esc($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn btn-sm ' . esc($class) . '">' . esc($label) . '</button></form>';
}

/** Convert 'Y-m-d H:i:s' (or '') to a datetime-local input value. */
function dt_local(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/** Convert datetime-local input to 'Y-m-d H:i:s' or null. */
function dt_from_local(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    $ts = strtotime($input);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/**
 * Build a checklist result from [label, pass, hint, weight] tuples.
 * Returns ['items' => [...], 'score' => 0-100].
 */
function checklist_score(array $checks): array
{
    $items = [];
    $score = 0;
    foreach ($checks as $c) {
        $label = (string) ($c[0] ?? '');
        $pass = (bool) ($c[1] ?? false);
        $hint = (string) ($c[2] ?? '');
        $w = (int) ($c[3] ?? 0);
        $items[] = ['label' => $label, 'pass' => $pass, 'hint' => $hint, 'weight' => $w];
        if ($pass) {
            $score += $w;
        }
    }
    return ['items' => $items, 'score' => min(100, $score)];
}

/** Render a checklist panel (score bar + items + guidance-only note). */
function checklist_panel(array $check, string $note = ''): string
{
    $score = (int) $check['score'];
    $color = $score >= 80 ? '#059669' : ($score >= 60 ? '#d97706' : '#dc2626');
    $html = '<div class="panel checklist-panel"><h2>Content checklist</h2>';
    $html .= '<div class="score-row"><div class="score-bar"><div class="score-fill" style="width:' . $score . '%;background:' . $color . '"></div></div>';
    $html .= '<div class="score-num" style="color:' . $color . '">' . $score . '/100</div></div>';
    $html .= '<ul class="checklist">';
    foreach ($check['items'] as $it) {
        $html .= '<li><span class="' . ($it['pass'] ? 'ok' : 'no') . '">' . ($it['pass'] ? '&#10003;' : '&#10007;') . '</span> <span><strong>' . esc($it['label']) . '</strong>';
        if ($it['hint'] !== '') {
            $html .= '<br><span class="hint">' . esc($it['hint']) . '</span>';
        }
        $html .= '</span></li>';
    }
    $html .= '</ul><p class="hint">Guidance only — this score is not a search ranking claim.</p>';
    if ($note !== '') {
        $html .= '<p class="hint">' . esc($note) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

/* ------------------------------------------------------------------ */
/* Option helpers for new recipe / article fields                     */
/* ------------------------------------------------------------------ */

/** Diet type <option> list. */
function diet_type_options(string $selected): string
{
    $types = [
        '' => '— None —',
        'veg' => 'Vegetarian',
        'non-veg' => 'Non-Vegetarian',
        'vegan' => 'Vegan',
        'eggetarian' => 'Eggetarian',
        'jain' => 'Jain',
    ];
    $out = '';
    foreach ($types as $v => $label) {
        $out .= '<option value="' . esc($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    return $out;
}

/** Cooking method <option> list. */
function cooking_method_options(string $selected): string
{
    $methods = [
        '' => '— None —', 'baking' => 'Baking', 'boiling' => 'Boiling',
        'deep-frying' => 'Deep Frying', 'grilling' => 'Grilling',
        'pan-frying' => 'Pan Frying', 'pressure-cooking' => 'Pressure Cooking',
        'roasting' => 'Roasting', 'sauteing' => 'Sautéing', 'steaming' => 'Steaming',
        'stir-frying' => 'Stir Frying', 'slow-cooking' => 'Slow Cooking',
        'microwave' => 'Microwave', 'no-cook' => 'No Cook / Raw',
        'tandoor' => 'Tandoor', 'smoking' => 'Smoking',
    ];
    $out = '';
    foreach ($methods as $v => $label) {
        $out .= '<option value="' . esc($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    return $out;
}

/** Skill level <option> list. */
function skill_level_options(string $selected): string
{
    $levels = ['' => '— None —', 'easy' => 'Easy', 'intermediate' => 'Intermediate', 'expert' => 'Expert'];
    $out = '';
    foreach ($levels as $v => $label) {
        $out .= '<option value="' . esc($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    return $out;
}

/** Course <option> list. */
function course_options(string $selected): string
{
    $courses = [
        '' => '— None —', 'breakfast' => 'Breakfast', 'lunch' => 'Lunch',
        'dinner' => 'Dinner', 'snack' => 'Snack', 'appetizer' => 'Appetizer',
        'soup' => 'Soup', 'salad' => 'Salad', 'dessert' => 'Dessert',
        'beverage' => 'Beverage', 'side-dish' => 'Side Dish',
    ];
    $out = '';
    foreach ($courses as $v => $label) {
        $out .= '<option value="' . esc($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    return $out;
}

/** Article type <option> list. */
function article_type_options(string $selected): string
{
    $types = [
        'article' => 'Article', 'food-news' => 'Food News',
        'kitchen-hack' => 'Kitchen Hack', 'health' => 'Health',
        'food-fact' => 'Food Fact', 'food-tip' => 'Food Tip',
        'trending' => 'Trending',
    ];
    $out = '';
    foreach ($types as $v => $label) {
        $out .= '<option value="' . esc($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . esc($label) . '</option>';
    }
    return $out;
}

