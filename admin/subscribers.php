<?php
/** FOODIIE admin — newsletter subscribers from storage/newsletter.csv. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$csv = storage_path('newsletter.csv');

function read_subscribers(string $csv): array
{
    if (!is_readable($csv)) {
        return [];
    }
    $rows = [];
    foreach (file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
        $cols = str_getcsv($line);
        if ($i === 0 && isset($cols[0]) && strtolower(trim($cols[0])) === 'email') {
            continue; // header row
        }
        $rows[] = [
            'email' => trim($cols[0] ?? ''),
            'date' => trim($cols[1] ?? ''),
            'source' => trim($cols[2] ?? ''),
        ];
    }
    return $rows;
}

// Download as CSV.
if (isset($_GET['download'])) {
    $rows = read_subscribers($csv);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'date', 'source']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['email'], $r['date'], $r['source']]);
    }
    fclose($out);
    exit;
}

$rows = read_subscribers($csv);

admin_head('Subscribers', 'subscribers', $current_user);
?>

<div class="panel">
  <h2>Newsletter subscribers</h2>
  <p><strong><?php echo count($rows); ?></strong> subscriber(s)</p>
  <p><a class="btn btn-secondary btn-sm" href="subscribers.php?download=1">Download CSV</a></p>
  <?php if (!$rows): ?>
    <p class="muted">No subscribers yet. Signups are stored in <code>storage/newsletter.csv</code>.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Email</th><th>Date</th><th>Source</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo esc($r['email']); ?></td>
          <td class="muted"><?php echo esc($r['date']); ?></td>
          <td class="muted"><?php echo esc($r['source'] ?: '—'); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php admin_foot(); ?>
