<?php
/** FOODIIE admin — create / edit a recipe. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../cms/functions/media.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$defaults = [
    'name' => '', 'slug' => '', 'description' => '', 'image' => '', 'image_alt' => '',
    'prep_time' => '', 'cook_time' => '', 'total_time' => '', 'servings' => '',
    'difficulty' => '', 'cuisine' => '', 'category_id' => null,
    'course' => '', 'diet_type' => '', 'cooking_method' => '', 'skill_level' => '',
    'ingredients_text' => '', 'instructions_text' => '',
    'calories' => '', 'protein' => '', 'carbs' => '', 'fat' => '',
    'tags' => '', 'status' => 'draft', 'publish_at' => '',
    'seo_title' => '', 'seo_description' => '', 'canonical' => '', 'og_image' => '',
    'category_ids' => [],
    'youtube_video_url' => '', 'youtube_video_id' => '', 'youtube_video_title' => '',
    'youtube_video_description' => '', 'youtube_channel_name' => '',
    'youtube_enabled' => 1, 'youtube_position' => 'after_intro', 'youtube_video_type' => 'normal',
];

$row = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Recipe not found.');
        redirect('recipes.php');
    }
    $ings = json_decode((string) $row['ingredients'], true);
    $steps = json_decode((string) $row['instructions'], true);
    $row['ingredients_text'] = is_array($ings) ? implode("\n", $ings) : '';
    $row['instructions_text'] = is_array($steps) ? implode("\n", $steps) : '';
    $row['category_ids'] = rc_for_recipe($id);
}

/** Textarea (one per line) → clean array. */
function lines_to_array(string $text): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return array_values($out);
}

$errors = [];
$needs_confirm = false;
$data = $row ?: $defaults;
$check = recipe_checklist($data);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $image = trim($_POST['image'] ?? '');
    if (isset($_FILES['image_upload']) && ($_FILES['image_upload']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$ok, $msg, $mrow] = handle_upload($_FILES['image_upload'], trim($_POST['image_alt'] ?? ''));
        if ($ok && $mrow) {
            $image = $mrow['filename'];
            flash('success', 'Image uploaded.');
        } else {
            $errors[] = $msg;
        }
    }

    $category_ids = array_values(array_filter(array_map('intval', (array) ($_POST['category_ids'] ?? []))));
    $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : ($category_ids[0] ?? null);

    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'image' => $image,
        'image_alt' => trim($_POST['image_alt'] ?? ''),
        'category_id' => $category_id,
        'category_ids' => $category_ids,
        'course' => trim($_POST['course'] ?? ''),
        'diet_type' => trim($_POST['diet_type'] ?? ''),
        'cooking_method' => trim($_POST['cooking_method'] ?? ''),
        'skill_level' => trim($_POST['skill_level'] ?? ''),
        'prep_time' => trim($_POST['prep_time'] ?? ''),
        'cook_time' => trim($_POST['cook_time'] ?? ''),
        'total_time' => trim($_POST['total_time'] ?? ''),
        'servings' => trim($_POST['servings'] ?? ''),
        'difficulty' => in_array($_POST['difficulty'] ?? '', ['Easy', 'Medium', 'Hard'], true) ? $_POST['difficulty'] : '',
        'cuisine' => trim($_POST['cuisine'] ?? ''),
        'ingredients_text' => trim($_POST['ingredients'] ?? ''),
        'instructions_text' => trim($_POST['instructions'] ?? ''),
        'calories' => trim($_POST['calories'] ?? ''),
        'protein' => trim($_POST['protein'] ?? ''),
        'carbs' => trim($_POST['carbs'] ?? ''),
        'fat' => trim($_POST['fat'] ?? ''),
        'tags' => trim($_POST['tags'] ?? ''),
        'status' => in_array($_POST['status'] ?? '', ['draft', 'review', 'scheduled', 'published', 'archived'], true) ? $_POST['status'] : 'draft',
        'publish_at' => dt_from_local($_POST['publish_at'] ?? ''),
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
        'canonical' => trim($_POST['canonical'] ?? ''),
        'og_image' => trim($_POST['og_image'] ?? ''),
    ];

    $yt_url = trim($_POST['youtube_video_url'] ?? '');
    $yt_id = '';
    $yt_type = 'normal';
    if ($yt_url !== '') {
        $yt_id = youtube_id($yt_url);
        if ($yt_id === '') {
            $errors[] = 'Please enter a valid YouTube video URL.';
        } else {
            $yt_type = youtube_type($yt_url);
        }
    }
    $yt_enabled = !empty($_POST['youtube_enabled']) ? 1 : 0;
    $yt_position = in_array($_POST['youtube_position'] ?? '', ['after_intro', 'after_ingredients', 'after_instructions', 'before_related'], true)
        ? $_POST['youtube_position']
        : 'after_intro';

    $data['youtube_video_url'] = $yt_url;
    $data['youtube_video_id'] = $yt_id;
    $data['youtube_video_title'] = trim($_POST['youtube_video_title'] ?? '');
    $data['youtube_video_description'] = trim($_POST['youtube_video_description'] ?? '');
    $data['youtube_channel_name'] = trim($_POST['youtube_channel_name'] ?? '');
    $data['youtube_enabled'] = $yt_enabled;
    $data['youtube_position'] = $yt_position;
    $data['youtube_video_type'] = $yt_type;

    if ($data['name'] === '') {
        $errors[] = 'Name is required.';
    }
    if ($data['slug'] === '') {
        $data['slug'] = slugify($data['name']);
    }
    $data['slug'] = unique_slug($pdo, 'recipes', $data['slug'], $id > 0 ? $id : null);
    $data['_ingredients'] = lines_to_array($data['ingredients_text']);
    $data['_instructions'] = lines_to_array($data['instructions_text']);
    $data['previewed'] = $id > 0 && !empty($_SESSION['previewed']['recipe'][$id]);

    $check = recipe_checklist($data);

    if ($data['status'] === 'published' && $check['score'] < 60 && empty($_POST['confirm_publish']) && !$errors) {
        $needs_confirm = true;
    }

    $save_preview = isset($_POST['save_preview']);

    if (!$errors && !$needs_confirm) {
        $now = now_utc();
        $params = [
            ':name' => $data['name'], ':slug' => $data['slug'],
            ':description' => $data['description'], ':image' => $data['image'], ':image_alt' => $data['image_alt'],
            ':category_id' => $data['category_id'],
            ':course' => $data['course'], ':diet_type' => $data['diet_type'],
            ':cooking_method' => $data['cooking_method'], ':skill_level' => $data['skill_level'],
            ':prep_time' => $data['prep_time'], ':cook_time' => $data['cook_time'], ':total_time' => $data['total_time'],
            ':servings' => $data['servings'], ':difficulty' => $data['difficulty'], ':cuisine' => $data['cuisine'],
            ':ingredients' => json_encode($data['_ingredients'], JSON_UNESCAPED_UNICODE),
            ':instructions' => json_encode($data['_instructions'], JSON_UNESCAPED_UNICODE),
            ':calories' => $data['calories'], ':protein' => $data['protein'],
            ':carbs' => $data['carbs'], ':fat' => $data['fat'],
            ':tags' => $data['tags'], ':status' => $data['status'],
            ':publish_at' => $data['publish_at'], ':u' => $now,
            ':seo_title' => $data['seo_title'], ':seo_description' => $data['seo_description'],
            ':canonical' => $data['canonical'], ':og_image' => $data['og_image'],
            ':youtube_video_url' => $data['youtube_video_url'],
            ':youtube_video_id' => $data['youtube_video_id'],
            ':youtube_video_title' => $data['youtube_video_title'],
            ':youtube_video_description' => $data['youtube_video_description'],
            ':youtube_channel_name' => $data['youtube_channel_name'],
            ':youtube_enabled' => $data['youtube_enabled'],
            ':youtube_position' => $data['youtube_position'],
            ':youtube_video_type' => $data['youtube_video_type'],
        ];
        if ($id > 0) {
            $params[':id'] = $id;
            $pdo->prepare('UPDATE recipes SET name=:name, slug=:slug, description=:description, image=:image, image_alt=:image_alt,
                category_id=:category_id, course=:course, diet_type=:diet_type, cooking_method=:cooking_method, skill_level=:skill_level,
                prep_time=:prep_time, cook_time=:cook_time, total_time=:total_time, servings=:servings, difficulty=:difficulty,
                cuisine=:cuisine, ingredients=:ingredients, instructions=:instructions, calories=:calories, protein=:protein,
                carbs=:carbs, fat=:fat, tags=:tags, status=:status, publish_at=:publish_at, updated_at=:u,
                seo_title=:seo_title, seo_description=:seo_description, canonical=:canonical, og_image=:og_image,
                youtube_video_url=:youtube_video_url, youtube_video_id=:youtube_video_id, youtube_video_title=:youtube_video_title,
                youtube_video_description=:youtube_video_description, youtube_channel_name=:youtube_channel_name,
                youtube_enabled=:youtube_enabled, youtube_position=:youtube_position, youtube_video_type=:youtube_video_type
                WHERE id=:id')->execute($params);
        } else {
            $params[':c'] = $now;
            $pdo->prepare('INSERT INTO recipes(name, slug, description, image, image_alt, category_id, course, diet_type,
                cooking_method, skill_level, prep_time, cook_time, total_time,
                servings, difficulty, cuisine, ingredients, instructions, calories, protein, carbs, fat, tags, status,
                publish_at, created_at, updated_at, seo_title, seo_description, canonical, og_image,
                youtube_video_url, youtube_video_id, youtube_video_title, youtube_video_description,
                youtube_channel_name, youtube_enabled, youtube_position, youtube_video_type)
                VALUES(:name, :slug, :description, :image, :image_alt, :category_id, :course, :diet_type,
                :cooking_method, :skill_level, :prep_time, :cook_time, :total_time,
                :servings, :difficulty, :cuisine, :ingredients, :instructions, :calories, :protein, :carbs, :fat, :tags, :status,
                :publish_at, :c, :u, :seo_title, :seo_description, :canonical, :og_image,
                :youtube_video_url, :youtube_video_id, :youtube_video_title, :youtube_video_description,
                :youtube_channel_name, :youtube_enabled, :youtube_position, :youtube_video_type)')->execute($params);
            $id = (int) $pdo->lastInsertId();
        }

        // Sync many-to-many categories
        rc_set_recipe($id, $category_ids);

        [$ok, $msg] = regen_site();
        flash($ok ? 'success' : 'warning', 'Recipe saved. ' . $msg);
        if ($save_preview) {
            redirect('preview.php?type=recipe&id=' . $id);
        }
        redirect('recipes.php');
    }
}

/** Content checklist for a recipe. */
function recipe_checklist(array $d): array
{
    $ings = $d['_ingredients'] ?? lines_to_array((string) ($d['ingredients_text'] ?? ''));
    $steps = $d['_instructions'] ?? lines_to_array((string) ($d['instructions_text'] ?? ''));
    $times = trim((string) ($d['prep_time'] ?? '') . ($d['cook_time'] ?? '') . ($d['total_time'] ?? '')) !== '';

    return checklist_score([
        ['Name set', trim((string) ($d['name'] ?? '')) !== '', 'Give the recipe a clear name.', 25],
        ['Image set', trim((string) ($d['image'] ?? '')) !== '', 'Pick an image from the media library.', 25],
        ['2+ ingredients', count($ings) >= 2, count($ings) . ' ingredient(s) listed.', 20],
        ['2+ steps', count($steps) >= 2, count($steps) . ' step(s) listed.', 20],
        ['Times set', $times, 'Add prep / cook / total time.', 10],
    ]);
}

function difficulty_options(string $selected): string
{
    $out = '<option value="">— Select —</option>';
    foreach (['Easy', 'Medium', 'Hard'] as $v) {
        $out .= '<option value="' . $v . '"' . ($v === $selected ? ' selected' : '') . '>' . $v . '</option>';
    }
    return $out;
}

$title = $id > 0 ? 'Edit Recipe' : 'New Recipe';
admin_head($title, 'recipes', $current_user);
?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?php echo esc($e); ?></div>
<?php endforeach; ?>

<?php if ($needs_confirm): ?>
  <div class="alert alert-warning">
    <strong>Heads up:</strong> the content checklist score is <?php echo (int) $check['score']; ?>/100 (below 60).
    Tick the confirmation below and save again to publish anyway.
  </div>
<?php endif; ?>

<form method="post" action="recipe-edit.php<?php echo $id > 0 ? '?id=' . $id : ''; ?>" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<div class="edit-layout">
  <div>
    <div class="panel">
      <div class="form-row">
        <div class="field">
          <label for="f-name">Name *</label>
          <input type="text" id="f-name" name="name" value="<?php echo esc($data['name']); ?>" required>
        </div>
        <div class="field">
          <label for="f-slug">Slug</label>
          <input type="text" id="f-slug" name="slug" value="<?php echo esc($data['slug']); ?>">
          <div class="hint">Leave blank to auto-generate from the name.</div>
        </div>
      </div>
      <div class="field">
        <label for="f-description">Description</label>
        <textarea id="f-description" name="description"><?php echo esc($data['description']); ?></textarea>
      </div>
      <div class="field">
        <label for="f-tags">Tags</label>
        <input type="text" id="f-tags" name="tags" value="<?php echo esc($data['tags']); ?>">
        <div class="hint">Comma-separated.</div>
      </div>
    </div>

    <div class="panel">
      <h2>Image</h2>
      <div class="field">
        <label for="f-image">Media library</label>
        <select id="f-image" name="image"><?php echo media_options($pdo, (string) $data['image']); ?></select>
      </div>
      <div class="field">
        <label for="f-image-upload">Or upload new</label>
        <input type="file" id="f-image-upload" name="image_upload" accept="image/*">
      </div>
      <div class="field">
        <label for="f-image-alt">Image alt text</label>
        <input type="text" id="f-image-alt" name="image_alt" value="<?php echo esc($data['image_alt']); ?>">
      </div>
    </div>

    <div class="panel">
      <h2>Details</h2>
      <div class="form-row-3">
        <div class="field">
          <label for="f-prep">Prep time</label>
          <input type="text" id="f-prep" name="prep_time" value="<?php echo esc($data['prep_time']); ?>" placeholder="e.g. 30 mins">
        </div>
        <div class="field">
          <label for="f-cook">Cook time</label>
          <input type="text" id="f-cook" name="cook_time" value="<?php echo esc($data['cook_time']); ?>" placeholder="e.g. 45 mins">
        </div>
        <div class="field">
          <label for="f-total">Total time</label>
          <input type="text" id="f-total" name="total_time" value="<?php echo esc($data['total_time']); ?>" placeholder="e.g. 1 hr 15 mins">
        </div>
      </div>
      <div class="form-row-3">
        <div class="field">
          <label for="f-servings">Servings</label>
          <input type="text" id="f-servings" name="servings" value="<?php echo esc($data['servings']); ?>" placeholder="e.g. 4">
        </div>
        <div class="field">
          <label for="f-difficulty">Difficulty</label>
          <select id="f-difficulty" name="difficulty"><?php echo difficulty_options((string) $data['difficulty']); ?></select>
        </div>
        <div class="field">
          <label for="f-cuisine">Cuisine</label>
          <input type="text" id="f-cuisine" name="cuisine" value="<?php echo esc($data['cuisine']); ?>" placeholder="e.g. North Indian">
        </div>
      </div>
      <div class="form-row-3">
        <div class="field">
          <label for="f-course">Course / Meal</label>
          <select id="f-course" name="course"><?php echo course_options((string) ($data['course'] ?? '')); ?></select>
        </div>
        <div class="field">
          <label for="f-diet">Diet Type</label>
          <select id="f-diet" name="diet_type"><?php echo diet_type_options((string) ($data['diet_type'] ?? '')); ?></select>
        </div>
        <div class="field">
          <label for="f-method">Cooking Method</label>
          <select id="f-method" name="cooking_method"><?php echo cooking_method_options((string) ($data['cooking_method'] ?? '')); ?></select>
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="f-skill">Skill Level</label>
          <select id="f-skill" name="skill_level"><?php echo skill_level_options((string) ($data['skill_level'] ?? '')); ?></select>
        </div>
        <div class="field">
          <label for="f-cat-primary">Primary Recipe Category</label>
          <select id="f-cat-primary" name="category_id">
            <option value="">— Select primary —</option>
            <?php echo rc_options(null, $data['category_id']); ?>
          </select>
        </div>
      </div>
    </div>

    <div class="panel">
      <h2>Recipe Categories (Cuisines, Courses, Diets, Ingredients, Styles)</h2>
      <div class="field">
        <label>Select all relevant categories &amp; tags:</label>
        <?php echo rc_checkboxes('category_ids', $data['category_ids']); ?>
        <div class="hint">These determine which category archives and mega-menu links this recipe appears under.</div>
      </div>
    </div>

    <div class="panel">
      <h2>Ingredients &amp; instructions</h2>
      <div class="field">
        <label for="f-ingredients">Ingredients (one per line)</label>
        <textarea id="f-ingredients" name="ingredients"><?php echo esc($data['ingredients_text']); ?></textarea>
      </div>
      <div class="field">
        <label for="f-instructions">Instructions (one step per line)</label>
        <textarea id="f-instructions" name="instructions"><?php echo esc($data['instructions_text']); ?></textarea>
      </div>
    </div>

    <div class="panel">
      <h2>Nutrition (optional — only enter verified values, never invent)</h2>
      <div class="form-row-3">
        <div class="field"><label for="f-cal">Calories</label><input type="text" id="f-cal" name="calories" value="<?php echo esc($data['calories']); ?>"></div>
        <div class="field"><label for="f-pro">Protein</label><input type="text" id="f-pro" name="protein" value="<?php echo esc($data['protein']); ?>"></div>
        <div class="field"><label for="f-carb">Carbs</label><input type="text" id="f-carb" name="carbs" value="<?php echo esc($data['carbs']); ?>"></div>
        <div class="field"><label for="f-fat">Fat</label><input type="text" id="f-fat" name="fat" value="<?php echo esc($data['fat']); ?>"></div>
      </div>
    </div>

    <!-- Recipe Video (YouTube Integration) -->
    <div class="panel" id="panel-recipe-video">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.5rem;">
        <h2 style="margin:0;">Recipe Video (YouTube Integration)</h2>
        <span class="badge" style="background:#FFE000;color:#202421;font-weight:800;padding:.25rem .75rem;border-radius:999px;">Official Embed</span>
      </div>
      <p class="hint">Embed an official step-by-step YouTube cooking video. Foodiie streams videos directly via YouTube's privacy-enhanced embed player.</p>

      <div class="field">
        <label for="f-yt-url">YouTube Video URL</label>
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
          <input type="text" id="f-yt-url" name="youtube_video_url" value="<?php echo esc($data['youtube_video_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/... or https://www.youtube.com/shorts/..." style="flex:1;min-width:280px;">
          <button type="button" class="btn btn-secondary" id="btn-preview-video" style="flex-shrink:0;">📺 Preview Video</button>
        </div>
        <div class="hint">Supported formats: <code>youtube.com/watch?v=ID</code>, <code>youtu.be/ID</code>, <code>youtube.com/shorts/ID</code>, or raw Video ID.</div>
      </div>

      <!-- Live Interactive Preview Container -->
      <div id="yt-preview-box" style="display:none;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;padding:1.25rem;margin:1.25rem 0;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;flex-wrap:wrap;gap:.5rem;">
          <div style="display:flex;align-items:center;gap:.5rem;">
            <span class="badge" style="background:#16A34A;color:#fff;font-weight:750;padding:.2rem .6rem;border-radius:999px;" id="yt-preview-status">✓ Valid YouTube URL</span>
            <span class="badge" style="background:#202421;color:#FFE000;font-weight:750;padding:.2rem .6rem;border-radius:999px;" id="yt-preview-type">Normal (16:9)</span>
          </div>
          <span style="font-size:.85rem;color:#6B7280;">Video ID: <strong id="yt-preview-id" style="font-family:monospace;color:#111827;"></strong></span>
        </div>
        <div id="yt-preview-player-wrap" style="position:relative;width:100%;max-width:640px;margin:0 auto;border-radius:12px;overflow:hidden;background:#000;box-shadow:0 4px 18px rgba(0,0,0,.15);aspect-ratio:16/9;">
          <iframe id="yt-preview-iframe" src="about:blank" style="width:100%;height:100%;border:0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="f-yt-title">Video Title (Optional)</label>
          <input type="text" id="f-yt-title" name="youtube_video_title" value="<?php echo esc($data['youtube_video_title'] ?? ''); ?>" placeholder="e.g. Quick Paneer Sandwich Recipe Step-by-Step">
        </div>
        <div class="field">
          <label for="f-yt-creator">Channel / Creator Name (Optional)</label>
          <input type="text" id="f-yt-creator" name="youtube_channel_name" value="<?php echo esc($data['youtube_channel_name'] ?? ''); ?>" placeholder="e.g. Sanjeev Kapoor Khazana">
          <div class="hint">Displayed as &ldquo;Video by [Creator Name]&rdquo; with an attribution link.</div>
        </div>
      </div>

      <div class="field">
        <label for="f-yt-desc">Short Video Note / Description (Optional)</label>
        <textarea id="f-yt-desc" name="youtube_video_description" rows="2" placeholder="Brief note about what this video demonstrates..."><?php echo esc($data['youtube_video_description'] ?? ''); ?></textarea>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="f-yt-position">Video Placement on Recipe Page</label>
          <select id="f-yt-position" name="youtube_position">
            <option value="after_intro" <?php echo ($data['youtube_position'] ?? '') === 'after_intro' ? 'selected' : ''; ?>>After Recipe Introduction</option>
            <option value="after_ingredients" <?php echo ($data['youtube_position'] ?? '') === 'after_ingredients' ? 'selected' : ''; ?>>After Ingredients List</option>
            <option value="after_instructions" <?php echo ($data['youtube_position'] ?? '') === 'after_instructions' ? 'selected' : ''; ?>>After Step-by-Step Instructions</option>
            <option value="before_related" <?php echo ($data['youtube_position'] ?? '') === 'before_related' ? 'selected' : ''; ?>>Before Related Recipes</option>
          </select>
        </div>
        <div class="field" style="display:flex;align-items:flex-end;padding-bottom:.5rem;">
          <label class="checkbox-row" style="margin:0;font-weight:700;">
            <input type="checkbox" name="youtube_enabled" value="1" <?php echo ($data['youtube_enabled'] ?? 1) ? 'checked' : ''; ?>>
            Show YouTube Video on Public Page
          </label>
        </div>
      </div>

      <!-- Help / Copyright Documentation Box -->
      <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:1rem 1.15rem;margin-top:1.25rem;font-size:.88rem;color:#78350F;line-height:1.5;">
        <strong style="display:block;margin-bottom:.35rem;color:#92400E;">📖 How to add a YouTube recipe video:</strong>
        <ol style="margin:0 0 .5rem 1.2rem;padding:0;">
          <li>Open the recipe on YouTube and copy its web link.</li>
          <li>Paste the URL into the <strong>YouTube Video URL</strong> field above.</li>
          <li>Click <strong>Preview Video</strong> to test and verify playback.</li>
          <li>Save the recipe. The static generator will automatically embed the official player.</li>
        </ol>
        <span style="font-size:.82rem;color:#B45309;">⚠️ <em>Foodiie embeds publicly available videos directly from YouTube via the official embed player. Do not download, copy, or upload third-party content without permission.</em></span>
      </div>
    </div>

    <div class="panel">
      <h2>Publishing &amp; SEO</h2>
      <div class="form-row">
        <div class="field">
          <label for="f-status">Status</label>
          <select id="f-status" name="status"><?php echo status_options((string) $data['status']); ?></select>
        </div>
        <div class="field">
          <label for="f-publish-at">Publish at</label>
          <input type="datetime-local" id="f-publish-at" name="publish_at" value="<?php echo esc(dt_local($data['publish_at'])); ?>">
        </div>
      </div>
      <div class="field">
        <label for="f-seo-title">SEO title</label>
        <input type="text" id="f-seo-title" name="seo_title" value="<?php echo esc($data['seo_title']); ?>">
      </div>
      <div class="field">
        <label for="f-seo-desc">SEO description</label>
        <textarea id="f-seo-desc" name="seo_description"><?php echo esc($data['seo_description']); ?></textarea>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="f-canonical">Canonical URL</label>
          <input type="url" id="f-canonical" name="canonical" value="<?php echo esc($data['canonical'] ?? ''); ?>" placeholder="https://...">
        </div>
        <div class="field">
          <label for="f-og-image">OG / Social Share Image</label>
          <select id="f-og-image" name="og_image"><?php echo media_options($pdo, (string) ($data['og_image'] ?? '')); ?></select>
        </div>
      </div>
    </div>

    <?php if ($needs_confirm): ?>
      <div class="panel">
        <label class="checkbox-row">
          <input type="checkbox" name="confirm_publish" value="1">
          I understand the checklist score is below 60 — publish anyway.
        </label>
      </div>
    <?php endif; ?>

    <div class="toolbar">
      <button type="submit" name="save" value="1" class="btn btn-primary">Save</button>
      <button type="submit" name="save_preview" value="1" class="btn btn-secondary">Save &amp; Preview</button>
      <?php if ($id > 0): ?>
        <a class="btn btn-ghost" href="preview.php?type=recipe&id=<?php echo $id; ?>" target="_blank" rel="noopener">Preview</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="recipes.php">Cancel</a>
    </div>
  </div>

  <div>
    <?php echo checklist_panel($check); ?>
  </div>
</div>
</form>

<script>
(function() {
  var urlInput = document.getElementById('f-yt-url');
  var previewBtn = document.getElementById('btn-preview-video');
  var previewBox = document.getElementById('yt-preview-box');
  var iframe = document.getElementById('yt-preview-iframe');
  var idDisplay = document.getElementById('yt-preview-id');
  var statusBadge = document.getElementById('yt-preview-status');
  var typeBadge = document.getElementById('yt-preview-type');
  var playerWrap = document.getElementById('yt-preview-player-wrap');

  function parseYouTube(url) {
    url = (url || '').trim();
    if (!url) return null;
    if (/^[A-Za-z0-9_-]{11}$/.test(url)) {
      return { id: url, type: 'normal' };
    }
    var isShorts = /\/shorts\//i.test(url);
    var regExp = /(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/))([A-Za-z0-9_-]{11})/i;
    var match = url.match(regExp);
    if (match && match[1]) {
      return { id: match[1], type: isShorts ? 'shorts' : 'normal' };
    }
    return null;
  }

  function renderPreview(force) {
    if (!urlInput || !previewBox) return;
    var info = parseYouTube(urlInput.value);
    if (!info) {
      if (force) {
        alert('Please enter a valid YouTube video URL (e.g. https://www.youtube.com/watch?v=... or https://youtu.be/...)');
      }
      previewBox.style.display = 'none';
      if (iframe) iframe.src = 'about:blank';
      return;
    }

    idDisplay.textContent = info.id;
    if (info.type === 'shorts') {
      typeBadge.textContent = 'Shorts (9:16)';
      typeBadge.style.background = '#DC2626';
      typeBadge.style.color = '#fff';
      playerWrap.style.aspectRatio = '9/16';
      playerWrap.style.maxWidth = '320px';
    } else {
      typeBadge.textContent = 'Normal (16:9)';
      typeBadge.style.background = '#202421';
      typeBadge.style.color = '#FFE000';
      playerWrap.style.aspectRatio = '16/9';
      playerWrap.style.maxWidth = '640px';
    }

    iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(info.id) + '?rel=0';
    previewBox.style.display = 'block';
  }

  if (previewBtn) {
    previewBtn.addEventListener('click', function(e) {
      e.preventDefault();
      renderPreview(true);
    });
  }

  // Pre-load preview if existing video URL is present
  if (urlInput && urlInput.value.trim() !== '') {
    renderPreview(false);
  }
})();
</script>

<?php admin_foot(); ?>
