<?php
/**
 * FOODIIE admin — page chrome (sidebar + topbar).
 * Include after guard.php: require_once __DIR__ . '/includes/layout.php';
 */

function admin_nav_items(): array
{
    return [
        ['dashboard.php', 'Dashboard', 'dashboard'],
        ['homepage-settings.php', 'Homepage Settings', 'homepage-settings'],
        ['articles.php', 'Articles', 'articles'],
        ['recipes.php', 'Recipes', 'recipes'],
        ['videos.php', 'Videos', 'videos'],
        ['shorts.php', 'Shorts', 'shorts'],
        ['recipe-categories.php', 'Recipe Categories', 'recipe-categories'],
        ['categories.php', 'Categories', 'categories'],
        ['media.php', 'Media', 'media'],
        ['ads.php', 'Ads', 'ads'],
        ['seo.php', 'SEO & Settings', 'seo'],
        ['pages.php', 'Pages', 'pages'],
        ['subscribers.php', 'Subscribers', 'subscribers'],
        ['backup.php', 'Backup', 'backup'],
    ];
}

function admin_head(string $title, string $active = '', ?array $user = null): void
{
    if ($user === null) {
        $user = $GLOBALS['current_user'] ?? null;
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc($title); ?> — FOODIIE Admin</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="brand"><span class="brand-mark">F</span> FOODIIE <span class="brand-sub">Admin</span></div>
    <nav class="nav">
      <?php foreach (admin_nav_items() as $item): ?>
        <a href="<?php echo esc($item[0]); ?>" class="<?php echo $item[2] === $active ? 'active' : ''; ?>"><?php echo esc($item[1]); ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <a href="../public/" target="_blank" rel="noopener">View Site &#8599;</a>
      <a href="logout.php">Logout</a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <h1><?php echo esc($title); ?></h1>
      <?php if ($user): ?>
      <div class="user-chip"><?php echo esc($user['name'] ?? 'Admin'); ?><span class="role"><?php echo esc($user['role'] ?? ''); ?></span></div>
      <?php endif; ?>
    </header>
    <main class="content">
      <?php echo render_flashes(); ?>
    <?php
}

function admin_foot(): void
{
    ?>
    </main>
  </div>
</div>
</body>
</html>
    <?php
}
