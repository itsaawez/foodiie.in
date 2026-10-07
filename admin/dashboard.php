<?php
/** FOODIIE admin dashboard. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

$counts = [
    'articles'   => (int) $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn(),
    'published'  => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn(),
    'drafts'     => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'draft'")->fetchColumn(),
    'recipes'    => (int) $pdo->query("SELECT COUNT(*) FROM recipes")->fetchColumn(),
    'videos'     => (int) $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn(),
    'shorts'     => (int) $pdo->query("SELECT COUNT(*) FROM shorts")->fetchColumn(),
    'categories' => (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'recipe_cats'=> (int) $pdo->query("SELECT COUNT(*) FROM recipe_categories")->fetchColumn(),
];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM articles WHERE status = 'scheduled' AND publish_at IS NOT NULL AND publish_at <= :now");
$stmt->execute([':now' => now_utc()]);
$counts['scheduled_due'] = (int) $stmt->fetchColumn();

$recent = $pdo->query('SELECT a.id, a.title, a.status, a.updated_at, c.name AS category_name, u.name AS author_name
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    LEFT JOIN users u ON u.id = a.author_id
    ORDER BY a.updated_at DESC LIMIT 5')->fetchAll();

admin_head('Dashboard', 'dashboard', $current_user);
?>

<div class="cards">
  <div class="card"><div class="num"><?php echo $counts['articles']; ?></div><div class="label">Total Articles</div></div>
  <div class="card"><div class="num"><?php echo $counts['published']; ?></div><div class="label">Published Articles</div></div>
  <div class="card"><div class="num"><?php echo $counts['drafts']; ?></div><div class="label">Draft Articles</div></div>
  <div class="card"><div class="num"><?php echo $counts['recipes']; ?></div><div class="label">Recipes</div></div>
  <div class="card"><div class="num"><?php echo $counts['videos']; ?></div><div class="label">Videos</div></div>
  <div class="card"><div class="num"><?php echo $counts['shorts']; ?></div><div class="label">Shorts</div></div>
  <div class="card"><div class="num"><?php echo $counts['recipe_cats']; ?></div><div class="label">Recipe Categories</div></div>
  <div class="card"><div class="num"><?php echo $counts['categories']; ?></div><div class="label">Site Categories</div></div>
  <div class="card"><div class="num"><?php echo $counts['scheduled_due']; ?></div><div class="label">Scheduled — due now</div></div>
</div>

<div class="panel">
  <h2>Quick actions</h2>
  <div class="toolbar" style="margin-bottom:0">
    <a class="btn btn-primary" href="article-edit.php">+ New Article</a>
    <a class="btn btn-secondary" href="recipe-edit.php">+ New Recipe</a>
    <a class="btn btn-secondary" href="video-edit.php">+ Add Video</a>
    <a class="btn btn-secondary" href="short-edit.php">+ Add Short</a>
    <a class="btn btn-secondary" href="categories.php">+ Add Category</a>
    <span class="spacer"></span>
    <form method="post" action="regenerate.php" class="inline-form">
      <?php echo csrf_field(); ?>
      <button type="submit" class="btn btn-secondary">Regenerate site</button>
    </form>
  </div>
  <p class="hint">Regenerating rebuilds the static public site from the database.</p>
</div>

<div class="panel">
  <h2>Recent articles</h2>
  <?php if (!$recent): ?>
    <p class="muted">No articles yet. <a href="article-edit.php">Write the first one</a>.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td class="row-title"><a href="article-edit.php?id=<?php echo (int) $r['id']; ?>"><?php echo esc($r['title']); ?></a></td>
            <td class="muted"><?php echo esc($r['category_name'] ?? '—'); ?></td>
            <td class="muted"><?php echo esc($r['author_name'] ?? '—'); ?></td>
            <td><?php echo status_badge((string) $r['status']); ?></td>
            <td class="muted"><?php echo esc(fmt_date($r['updated_at'])); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin:.75rem 0 0"><a href="articles.php">View all articles &rarr;</a></p>
  <?php endif; ?>
</div>

<?php admin_foot(); ?>
