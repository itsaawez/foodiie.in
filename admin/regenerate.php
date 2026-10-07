<?php
/** FOODIIE admin — regenerate the static site. POST only for the rebuild. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    [$ok, $msg] = regen_site();
    flash($ok ? 'success' : 'error', $msg);
    redirect('dashboard.php');
}

admin_head('Regenerate site', 'dashboard', $current_user);
?>
<div class="panel">
  <h2>Rebuild the static site</h2>
  <p>This regenerates every public page from the current database content. It can take a few seconds.</p>
  <form method="post" action="regenerate.php">
    <?php echo csrf_field(); ?>
    <button type="submit" class="btn btn-primary">Regenerate now</button>
    <a class="btn btn-ghost" href="dashboard.php">Cancel</a>
  </form>
</div>
<?php admin_foot(); ?>
