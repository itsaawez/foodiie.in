<?php
/** FOODIIE admin — create / edit a video. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$defaults = [
    'title' => '', 'slug' => '', 'youtube_url' => '', 'video_id' => '',
    'thumbnail' => '', 'description' => '', 'category_id' => '',
    'tags' => '', 'status' => 'draft', 'publish_at' => '',
    'seo_title' => '', 'seo_description' => '',
];

$row = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM videos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Video not found.');
        redirect('videos.php');
    }
}

$errors = [];
$needs_confirm = false;
$data = $row ?: $defaults;
$check = video_checklist($data);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'youtube_url' => trim($_POST['youtube_url'] ?? ''),
        'thumbnail' => trim($_POST['thumbnail'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null,
        'tags' => trim($_POST['tags'] ?? ''),
        'status' => in_array($_POST['status'] ?? '', ['draft', 'review', 'scheduled', 'published', 'archived'], true) ? $_POST['status'] : 'draft',
        'publish_at' => dt_from_local($_POST['publish_at'] ?? ''),
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
    ];
    if ($data['title'] === '') {
        $errors[] = 'Title is required.';
    }
    $data['video_id'] = $data['youtube_url'] !== '' ? youtube_id($data['youtube_url']) : '';
    if ($data['youtube_url'] !== '' && $data['video_id'] === '') {
        $errors[] = 'The YouTube URL is not valid. Paste a watch, share, embed or shorts URL.';
    }
    if ($data['slug'] === '') {
        $data['slug'] = slugify($data['title']);
    }
    $data['slug'] = unique_slug($pdo, 'videos', $data['slug'], $id > 0 ? $id : null);
    $data['previewed'] = $id > 0 && !empty($_SESSION['previewed']['video'][$id]);

    $check = video_checklist($data);

    if ($data['status'] === 'published' && $check['score'] < 60 && empty($_POST['confirm_publish']) && !$errors) {
        $needs_confirm = true;
    }

    $save_preview = isset($_POST['save_preview']);

    if (!$errors && !$needs_confirm) {
        $now = now_utc();
        $params = [
            ':title' => $data['title'], ':slug' => $data['slug'],
            ':youtube_url' => $data['youtube_url'], ':video_id' => $data['video_id'],
            ':thumbnail' => $data['thumbnail'], ':description' => $data['description'],
            ':category_id' => $data['category_id'], ':tags' => $data['tags'],
            ':status' => $data['status'], ':publish_at' => $data['publish_at'], ':u' => $now,
            ':seo_title' => $data['seo_title'], ':seo_description' => $data['seo_description'],
        ];
        if ($id > 0) {
            $params[':id'] = $id;
            $pdo->prepare('UPDATE videos SET title=:title, slug=:slug, youtube_url=:youtube_url, video_id=:video_id,
                thumbnail=:thumbnail, description=:description, category_id=:category_id, tags=:tags,
                status=:status, publish_at=:publish_at, updated_at=:u,
                seo_title=:seo_title, seo_description=:seo_description WHERE id=:id')->execute($params);
        } else {
            $params[':c'] = $now;
            $pdo->prepare('INSERT INTO videos(title, slug, youtube_url, video_id, thumbnail, description, category_id, tags,
                status, publish_at, created_at, updated_at, seo_title, seo_description)
                VALUES(:title, :slug, :youtube_url, :video_id, :thumbnail, :description, :category_id, :tags,
                :status, :publish_at, :c, :u, :seo_title, :seo_description)')->execute($params);
            $id = (int) $pdo->lastInsertId();
        }
        [$ok, $msg] = regen_site();
        flash($ok ? 'success' : 'warning', 'Video saved. ' . $msg);
        if ($save_preview) {
            redirect('preview.php?type=video&id=' . $id);
        }
        redirect('videos.php');
    }
}

/** Content checklist for a video. */
function video_checklist(array $d): array
{
    $desc_len = mb_strlen(trim((string) ($d['description'] ?? '')));
    return checklist_score([
        ['Title set', trim((string) ($d['title'] ?? '')) !== '', 'Give the video a clear title.', 25],
        ['Valid YouTube URL', youtube_id((string) ($d['youtube_url'] ?? '')) !== '', 'Paste a watch, share, embed or shorts URL.', 25],
        ['Description', $desc_len >= 60, "Aim for 60+ characters (now {$desc_len}).", 20],
        ['Category assigned', !empty($d['category_id']), 'Choose a category.', 15],
        ['Thumbnail set', trim((string) ($d['thumbnail'] ?? '')) !== '', 'Optional — the YouTube thumbnail is used otherwise.', 15],
    ]);
}

$title = $id > 0 ? 'Edit Video' : 'Add Video';
admin_head($title, 'videos', $current_user);
?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?php echo esc($e); ?></div>
<?php endforeach; ?>

<?php if ($needs_confirm): ?>
  <div class="alert alert-warning">
    <strong>Heads up:</strong> the checklist score is <?php echo (int) $check['score']; ?>/100 (below 60).
    Tick the confirmation below and save again to publish anyway.
  </div>
<?php endif; ?>

<form method="post" action="video-edit.php<?php echo $id > 0 ? '?id=' . $id : ''; ?>">
<?php echo csrf_field(); ?>
<div class="edit-layout">
  <div>
    <div class="panel">
      <div class="field">
        <label for="f-title">Title *</label>
        <input type="text" id="f-title" name="title" value="<?php echo esc($data['title']); ?>" required>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="f-slug">Slug</label>
          <input type="text" id="f-slug" name="slug" value="<?php echo esc($data['slug']); ?>">
          <div class="hint">Leave blank to auto-generate from the title.</div>
        </div>
        <div class="field">
          <label for="f-tags">Tags</label>
          <input type="text" id="f-tags" name="tags" value="<?php echo esc($data['tags']); ?>">
          <div class="hint">Comma-separated.</div>
        </div>
      </div>
      <div class="field">
        <label for="f-yt">YouTube URL *</label>
        <input type="url" id="f-yt" name="youtube_url" value="<?php echo esc($data['youtube_url']); ?>" placeholder="https://www.youtube.com/watch?v=…">
        <div class="hint">The video ID is extracted automatically on save.</div>
      </div>
      <?php if ($data['video_id'] !== ''): ?>
        <p class="hint">Detected video ID: <code><?php echo esc($data['video_id']); ?></code></p>
      <?php endif; ?>
      <div class="field">
        <label for="f-description">Description</label>
        <textarea id="f-description" name="description"><?php echo esc($data['description']); ?></textarea>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="f-category">Category</label>
          <select id="f-category" name="category_id"><?php echo category_options($pdo, $data['category_id']); ?></select>
        </div>
        <div class="field">
          <label for="f-thumb">Thumbnail</label>
          <select id="f-thumb" name="thumbnail"><?php echo media_options($pdo, (string) $data['thumbnail']); ?></select>
          <div class="hint">Optional — falls back to the YouTube thumbnail.</div>
        </div>
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
        <a class="btn btn-ghost" href="preview.php?type=video&id=<?php echo $id; ?>" target="_blank" rel="noopener">Preview</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="videos.php">Cancel</a>
    </div>
  </div>

  <div>
    <?php echo checklist_panel($check); ?>
  </div>
</div>
</form>

<?php admin_foot(); ?>
