<?php
/**
 * FOODIIE admin — create / edit a YouTube Short.
 */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../cms/functions/media.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$defaults = [
    'title' => '',
    'slug' => '',
    'youtube_url' => '',
    'video_id' => '',
    'thumbnail' => '',
    'description' => '',
    'tags' => '',
    'status' => 'draft',
    'publish_at' => '',
];

$row = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM shorts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Short not found.');
        redirect('shorts.php');
    }
}

$errors = [];
$data = $row ?: $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $yt_url = trim($_POST['youtube_url'] ?? '');
    $vid = youtube_id($yt_url);
    if ($vid === '' && preg_match('/^[A-Za-z0-9_-]{10,12}$/', $yt_url)) {
        $vid = $yt_url;
    }

    $thumb = trim($_POST['thumbnail'] ?? '');
    if ($thumb === '' && $vid !== '') {
        $thumb = "https://img.youtube.com/vi/{$vid}/hqdefault.jpg";
    }

    $data = [
        'title'       => trim($_POST['title'] ?? ''),
        'slug'        => trim($_POST['slug'] ?? ''),
        'youtube_url' => $yt_url,
        'video_id'    => $vid,
        'thumbnail'   => $thumb,
        'description' => trim($_POST['description'] ?? ''),
        'tags'        => trim($_POST['tags'] ?? ''),
        'status'      => in_array($_POST['status'] ?? '', ['draft', 'review', 'scheduled', 'published', 'archived'], true) ? $_POST['status'] : 'draft',
        'publish_at'  => dt_from_local($_POST['publish_at'] ?? ''),
    ];

    if ($data['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($data['youtube_url'] === '') {
        $errors[] = 'YouTube Short URL is required.';
    } elseif ($data['video_id'] === '') {
        $errors[] = 'Could not extract YouTube video ID from the provided URL. Paste a valid youtube.com/shorts/... or youtu.be/... link.';
    }

    if ($data['slug'] === '') {
        $data['slug'] = slugify($data['title']);
    }
    $data['slug'] = unique_slug($pdo, 'shorts', $data['slug'], $id > 0 ? $id : null);

    if (!$errors) {
        $now = now_utc();
        $params = [
            ':title'       => $data['title'],
            ':slug'        => $data['slug'],
            ':youtube_url' => $data['youtube_url'],
            ':video_id'    => $data['video_id'],
            ':thumbnail'   => $data['thumbnail'],
            ':description' => $data['description'],
            ':tags'        => $data['tags'],
            ':status'      => $data['status'],
            ':publish_at'  => $data['publish_at'],
            ':u'           => $now,
        ];
        if ($id > 0) {
            $params[':id'] = $id;
            $pdo->prepare('UPDATE shorts SET title=:title, slug=:slug, youtube_url=:youtube_url, video_id=:video_id,
                thumbnail=:thumbnail, description=:description, tags=:tags, status=:status,
                publish_at=:publish_at, updated_at=:u WHERE id=:id')->execute($params);
        } else {
            $params[':c'] = $now;
            $pdo->prepare('INSERT INTO shorts(title, slug, youtube_url, video_id, thumbnail, description, tags,
                status, publish_at, created_at, updated_at)
                VALUES(:title, :slug, :youtube_url, :video_id, :thumbnail, :description, :tags,
                :status, :publish_at, :c, :u)')->execute($params);
            $id = (int) $pdo->lastInsertId();
        }

        [$ok, $msg] = regen_site();
        flash($ok ? 'success' : 'warning', 'Short saved. ' . $msg);
        redirect('shorts.php');
    }
}

$title = $id > 0 ? 'Edit Short' : 'New Short';
admin_head($title, 'shorts', $current_user);
?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?php echo esc($e); ?></div>
<?php endforeach; ?>

<form method="post" action="short-edit.php<?php echo $id > 0 ? '?id=' . $id : ''; ?>">
<?php echo csrf_field(); ?>
<div class="edit-layout">
  <div>
    <div class="panel">
      <div class="form-row">
        <div class="field">
          <label for="s-title">Title *</label>
          <input type="text" id="s-title" name="title" value="<?php echo esc($data['title']); ?>" required placeholder="e.g. 60-Sec Garlic Naan Hack">
        </div>
        <div class="field">
          <label for="s-slug">Slug</label>
          <input type="text" id="s-slug" name="slug" value="<?php echo esc($data['slug']); ?>" placeholder="Auto-generated if empty">
        </div>
      </div>

      <div class="field">
        <label for="s-url">YouTube Short URL or ID *</label>
        <input type="url" id="s-url" name="youtube_url" value="<?php echo esc($data['youtube_url']); ?>" required
               placeholder="https://www.youtube.com/shorts/XXXXXXXXXXX or watch URL">
        <div class="hint">Accepts standard YouTube Shorts links, embed links, or normal watch links.</div>
      </div>

      <div class="field">
        <label for="s-desc">Description (caption / notes)</label>
        <textarea id="s-desc" name="description" rows="3"><?php echo esc($data['description']); ?></textarea>
      </div>

      <div class="field">
        <label for="s-tags">Tags</label>
        <input type="text" id="s-tags" name="tags" value="<?php echo esc($data['tags']); ?>" placeholder="quick recipes, naan, hack">
        <div class="hint">Comma-separated tags for search &amp; filtering.</div>
      </div>
    </div>

    <div class="panel">
      <h2>Thumbnail</h2>
      <div class="form-row">
        <div class="field">
          <label for="s-thumb">Custom Thumbnail URL (optional)</label>
          <input type="text" id="s-thumb" name="thumbnail" value="<?php echo esc($data['thumbnail']); ?>" placeholder="Leave blank to auto-use YouTube's HQ thumbnail">
        </div>
        <div class="field">
          <label for="s-media">Or pick from Media Library</label>
          <select id="s-media" onchange="if(this.value) document.getElementById('s-thumb').value = this.value;">
            <option value="">— Choose media image —</option>
            <?php echo media_options($pdo, (string) $data['thumbnail']); ?>
          </select>
        </div>
      </div>
    </div>

    <div class="panel">
      <h2>Publishing</h2>
      <div class="form-row">
        <div class="field">
          <label for="s-status">Status</label>
          <select id="s-status" name="status"><?php echo status_options((string) $data['status']); ?></select>
        </div>
        <div class="field">
          <label for="s-publish-at">Publish at</label>
          <input type="datetime-local" id="s-publish-at" name="publish_at" value="<?php echo esc(dt_local($data['publish_at'])); ?>">
        </div>
      </div>
    </div>

    <div class="toolbar">
      <button type="submit" class="btn btn-primary">Save Short</button>
      <a class="btn btn-ghost" href="shorts.php">Cancel</a>
    </div>
  </div>

  <div>
    <div class="panel">
      <h2>Short Preview</h2>
      <?php if (!empty($data['video_id'])): ?>
        <div style="position:relative;width:100%;max-width:280px;margin:0 auto;aspect-ratio:9/16;border-radius:12px;overflow:hidden;background:#000;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <iframe
            src="https://www.youtube.com/embed/<?php echo urlencode($data['video_id']); ?>?rel=0"
            title="Short Preview"
            style="width:100%;height:100%;border:0;"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen>
          </iframe>
        </div>
        <p class="hint" style="text-align:center;margin-top:.75rem;">
          Video ID: <code><?php echo esc($data['video_id']); ?></code>
        </p>
      <?php else: ?>
        <div style="aspect-ratio:9/16;max-width:240px;margin:0 auto;border:2px dashed #d1d5db;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#9ca3af;text-align:center;padding:1rem;">
          Paste a YouTube Short URL to preview player
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</form>

<?php admin_foot(); ?>
