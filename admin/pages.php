<?php
/** FOODIIE admin — static pages list. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();
$pages = $pdo->query('SELECT * FROM pages ORDER BY slug')->fetchAll();

$legal = ['privacy-policy', 'terms', 'disclaimer', 'cookie-policy', 'affiliate-disclosure'];

admin_head('Pages', 'pages', $current_user);
?>

<div class="panel">
  <h2>All pages</h2>
  <?php if (!$pages): ?>
    <p class="muted">No pages yet.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Title</th><th>Slug</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($pages as $p): ?>
        <tr>
          <td class="row-title">
            <a href="page-edit.php?id=<?php echo (int) $p['id']; ?>"><?php echo esc($p['title']); ?></a>
            <?php if (in_array($p['slug'], $legal, true)): ?>
              <span class="badge b-review">Template — legal review needed</span>
            <?php endif; ?>
          </td>
          <td class="muted"><?php echo esc($p['slug']); ?></td>
          <td class="muted"><?php echo esc(fmt_date($p['updated_at'])); ?></td>
          <td class="actions">
            <a class="btn btn-ghost btn-sm" href="page-edit.php?id=<?php echo (int) $p['id']; ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php admin_foot(); ?>
