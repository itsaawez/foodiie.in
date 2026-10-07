<?php
/** FOODIIE admin — create / edit an article. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../cms/functions/media.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

$defaults = [
    'title' => '', 'slug' => '', 'description' => '', 'body' => '',
    'image' => '', 'image_alt' => '', 'author_id' => $current_user['id'] ?? null,
    'category_id' => '', 'article_type' => 'article', 'tags' => '', 'status' => 'draft', 'publish_at' => '',
    'reading_time' => 0, 'seo_title' => '', 'seo_description' => '',
    'canonical' => '', 'og_image' => '',
];

$row = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        flash('error', 'Article not found.');
        redirect('articles.php');
    }
}

$errors = [];
$needs_confirm = false;
$data = $row ?: $defaults;
$check = seo_checklist($data);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Optional image upload (takes precedence over the library select).
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

    $valid_article_types = ['article', 'food-news', 'kitchen-hack', 'health', 'food-fact', 'food-tip', 'trending'];
    $article_type = in_array($_POST['article_type'] ?? '', $valid_article_types, true) ? $_POST['article_type'] : 'article';

    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'body' => sanitize_html($_POST['body'] ?? ''),
        'image' => $image,
        'image_alt' => trim($_POST['image_alt'] ?? ''),
        'author_id' => ($_POST['author_id'] ?? '') !== '' ? (int) $_POST['author_id'] : null,
        'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null,
        'article_type' => $article_type,
        'tags' => trim($_POST['tags'] ?? ''),
        'status' => in_array($_POST['status'] ?? '', ['draft', 'review', 'scheduled', 'published', 'archived'], true) ? $_POST['status'] : 'draft',
        'publish_at' => dt_from_local($_POST['publish_at'] ?? ''),
        'seo_title' => trim($_POST['seo_title'] ?? ''),
        'seo_description' => trim($_POST['seo_description'] ?? ''),
        'canonical' => trim($_POST['canonical'] ?? ''),
        'og_image' => trim($_POST['og_image'] ?? ''),
    ];
    if ($data['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if ($data['slug'] === '') {
        $data['slug'] = slugify($data['title']);
    }
    $data['slug'] = unique_slug($pdo, 'articles', $data['slug'], $id > 0 ? $id : null);
    $data['reading_time'] = reading_time($data['body']);
    $data['previewed'] = $id > 0 && !empty($_SESSION['previewed']['article'][$id]);

    $check = seo_checklist($data);

    if ($data['status'] === 'published' && $check['score'] < 60 && empty($_POST['confirm_publish']) && !$errors) {
        $needs_confirm = true;
    }

    $save_preview = isset($_POST['save_preview']);

    if (!$errors && !$needs_confirm) {
        $now = now_utc();
        $params = [
            ':title' => $data['title'], ':slug' => $data['slug'],
            ':description' => $data['description'], ':body' => $data['body'],
            ':image' => $data['image'], ':image_alt' => $data['image_alt'],
            ':author_id' => $data['author_id'], ':category_id' => $data['category_id'],
            ':at' => $data['article_type'],
            ':tags' => $data['tags'], ':status' => $data['status'],
            ':publish_at' => $data['publish_at'], ':u' => $now,
            ':rt' => $data['reading_time'],
            ':seo_title' => $data['seo_title'], ':seo_description' => $data['seo_description'],
            ':canonical' => $data['canonical'], ':og_image' => $data['og_image'],
        ];
        if ($id > 0) {
            $params[':id'] = $id;
            $pdo->prepare('UPDATE articles SET title=:title, slug=:slug, description=:description, body=:body,
                image=:image, image_alt=:image_alt, author_id=:author_id, category_id=:category_id,
                article_type=:at, tags=:tags,
                status=:status, publish_at=:publish_at, updated_at=:u, reading_time=:rt,
                seo_title=:seo_title, seo_description=:seo_description, canonical=:canonical, og_image=:og_image
                WHERE id=:id')->execute($params);
        } else {
            $params[':c'] = $now;
            $pdo->prepare('INSERT INTO articles(title, slug, description, body, image, image_alt, author_id, category_id,
                article_type, tags, status, publish_at, created_at, updated_at, reading_time, seo_title, seo_description, canonical, og_image)
                VALUES(:title, :slug, :description, :body, :image, :image_alt, :author_id, :category_id,
                :at, :tags, :status, :publish_at, :c, :u, :rt, :seo_title, :seo_description, :canonical, :og_image)')->execute($params);
            $id = (int) $pdo->lastInsertId();
        }
        [$ok, $msg] = regen_site();
        flash($ok ? 'success' : 'warning', 'Article saved. ' . $msg);
        if ($save_preview) {
            redirect('preview.php?type=article&id=' . $id);
        }
        redirect('articles.php');
    }
}

/** Content checklist for an article. */
function seo_checklist(array $d): array
{
    $body = (string) ($d['body'] ?? '');
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
    $words = $plain === '' ? 0 : count(preg_split('/\s+/', $plain));
    $internal = preg_match_all('#<a\s[^>]*href=["\'](/(?!/)|https?://)#i', $body);
    $desc_len = mb_strlen(trim((string) ($d['description'] ?? '')));

    return checklist_score([
        ['Title set', trim((string) ($d['title'] ?? '')) !== '', 'Write a clear, specific title.', 10],
        ['Slug set', trim((string) ($d['slug'] ?? '')) !== '', 'The slug becomes the article URL.', 10],
        ['Featured image', trim((string) ($d['image'] ?? '')) !== '', 'Pick an image from the media library.', 10],
        ['Image alt text', trim((string) ($d['image_alt'] ?? '')) !== '', 'Describe the image for screen readers.', 5],
        ['Description (excerpt)', $desc_len >= 60, "Aim for 60+ characters (now {$desc_len}).", 10],
        ['Category assigned', !empty($d['category_id']), 'Choose a category.', 5],
        ['Content 300+ words', $words >= 300, "Currently {$words} words.", 20],
        ['SEO title', trim((string) ($d['seo_title'] ?? '')) !== '', 'Used as the browser/search title.', 5],
        ['SEO description', trim((string) ($d['seo_description'] ?? '')) !== '', 'Shown in search results.', 5],
        ['Canonical URL', trim((string) ($d['canonical'] ?? '')) !== '', 'Optional — leave blank if not needed.', 5],
        ['Internal links', $internal >= 1, "Found {$internal} link(s).", 5],
        ['Previewed', !empty($d['previewed']), 'Open Preview before publishing.', 10],
    ]);
}

$title = $id > 0 ? 'Edit Article' : 'New Article';
admin_head($title, 'articles', $current_user);
?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-error"><?php echo esc($e); ?></div>
<?php endforeach; ?>

<?php if ($needs_confirm): ?>
  <div class="alert alert-warning">
    <strong>Heads up:</strong> the SEO checklist score is <?php echo (int) $check['score']; ?>/100 (below 60).
    Publishing now is allowed, but consider fixing the flagged items first. Tick the confirmation below and save again to publish anyway.
  </div>
<?php endif; ?>

<form method="post" action="article-edit.php<?php echo $id > 0 ? '?id=' . $id : ''; ?>" enctype="multipart/form-data">
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
          <div class="hint">Comma-separated, e.g. biryani, street food</div>
        </div>
      </div>
      <div class="field">
        <label for="f-description">Description (excerpt)</label>
        <textarea id="f-description" name="description"><?php echo esc($data['description']); ?></textarea>
      </div>
      <div class="field">
        <label for="f-body">Body (HTML allowed)</label>
        <textarea id="f-body" name="body" class="tall"><?php echo esc($data['body']); ?></textarea>
        <div class="hint">HTML is sanitized on save (allowed: headings, text, lists, links, images, tables, quotes, callout divs).</div>
      </div>
    </div>

    <div class="panel">
      <h2>Featured image</h2>
      <div class="field">
        <label for="f-image">Media library</label>
        <select id="f-image" name="image"><?php echo media_options($pdo, (string) $data['image']); ?></select>
      </div>
      <div class="field">
        <label for="f-image-upload">Or upload new</label>
        <input type="file" id="f-image-upload" name="image_upload" accept="image/*">
        <div class="hint">JPG, PNG, WebP, AVIF or GIF, max 8 MB.</div>
      </div>
      <div class="field">
        <label for="f-image-alt">Image alt text</label>
        <input type="text" id="f-image-alt" name="image_alt" value="<?php echo esc($data['image_alt']); ?>">
      </div>
      <div class="field">
        <label for="f-og">OG image</label>
        <input type="text" id="f-og" name="og_image" value="<?php echo esc($data['og_image']); ?>">
        <div class="hint">Optional override for social sharing (URL or media filename).</div>
      </div>
    </div>

    <div class="panel">
      <h2>Publishing</h2>
      <div class="form-row">
        <div class="field">
          <label for="f-status">Status</label>
          <select id="f-status" name="status"><?php echo status_options((string) $data['status']); ?></select>
        </div>
        <div class="field">
          <label for="f-publish-at">Publish at</label>
          <input type="datetime-local" id="f-publish-at" name="publish_at" value="<?php echo esc(dt_local($data['publish_at'])); ?>">
          <div class="hint">Leave blank to publish immediately (when status is Published).</div>
        </div>
      </div>
      <div class="form-row-3">
        <div class="field">
          <label for="f-author">Author</label>
          <select id="f-author" name="author_id"><?php echo user_options($pdo, $data['author_id']); ?></select>
        </div>
        <div class="field">
          <label for="f-category">Category</label>
          <select id="f-category" name="category_id"><?php echo category_options($pdo, $data['category_id']); ?></select>
        </div>
        <div class="field">
          <label for="f-type">Article Type / Feature</label>
          <select id="f-type" name="article_type"><?php echo article_type_options((string) ($data['article_type'] ?? 'article')); ?></select>
        </div>
      </div>
      <p class="hint">Estimated reading time: <strong><?php echo (int) (($data['reading_time'] ?? 0) ?: reading_time((string) $data['body'])); ?> min</strong> (recalculated on save).</p>
    </div>

    <div class="panel">
      <h2>SEO</h2>
      <div class="field">
        <label for="f-seo-title">SEO title</label>
        <input type="text" id="f-seo-title" name="seo_title" value="<?php echo esc($data['seo_title']); ?>">
      </div>
      <div class="field">
        <label for="f-seo-desc">SEO description</label>
        <textarea id="f-seo-desc" name="seo_description"><?php echo esc($data['seo_description']); ?></textarea>
      </div>
      <div class="field">
        <label for="f-canonical">Canonical URL</label>
        <input type="url" id="f-canonical" name="canonical" value="<?php echo esc($data['canonical']); ?>" placeholder="https://…">
        <div class="hint">Only set this if the content is duplicated elsewhere.</div>
      </div>
    </div>

    <?php if ($needs_confirm): ?>
      <div class="panel">
        <label class="checkbox-row">
          <input type="checkbox" name="confirm_publish" value="1">
          I understand the SEO score is below 60 — publish anyway.
        </label>
      </div>
    <?php endif; ?>

    <div class="toolbar">
      <button type="submit" name="save" value="1" class="btn btn-primary">Save</button>
      <button type="submit" name="save_preview" value="1" class="btn btn-secondary">Save &amp; Preview</button>
      <?php if ($id > 0): ?>
        <a class="btn btn-ghost" href="preview.php?type=article&id=<?php echo $id; ?>" target="_blank" rel="noopener">Preview</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="articles.php">Cancel</a>
    </div>
  </div>

  <div>
    <?php echo checklist_panel($check); ?>
  </div>
</div>
</form>

<?php admin_foot(); ?>
