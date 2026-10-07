<?php
/** FOODIIE admin — ad slot management. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../cms/functions/ads.php';

$pdo = db();
$slots = ad_slots();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $upsert = $pdo->prepare('INSERT INTO ads(slot, enabled, code, device, start_date, end_date)
        VALUES(:s, :e, :c, :d, :sd, :ed)
        ON CONFLICT(slot) DO UPDATE SET enabled = excluded.enabled, code = excluded.code,
        device = excluded.device, start_date = excluded.start_date, end_date = excluded.end_date');
    foreach ($slots as $slot => $label) {
        $in = $_POST['ads'][$slot] ?? [];
        if (!is_array($in)) {
            $in = [];
        }
        $device = $in['device'] ?? 'both';
        if (!in_array($device, ['desktop', 'mobile', 'both'], true)) {
            $device = 'both';
        }
        $upsert->execute([
            ':s' => $slot,
            ':e' => !empty($in['enabled']) ? 1 : 0,
            ':c' => (string) ($in['code'] ?? ''),
            ':d' => $device,
            ':sd' => trim((string) ($in['start_date'] ?? '')),
            ':ed' => trim((string) ($in['end_date'] ?? '')),
        ]);
    }
    [$ok, $msg] = regen_site();
    flash($ok ? 'success' : 'warning', 'Ad settings saved. ' . $msg);
    redirect('ads.php');
}

$ads = get_ads();

function device_options(string $selected): string
{
    $out = '';
    foreach (['desktop' => 'Desktop', 'mobile' => 'Mobile', 'both' => 'Both'] as $v => $label) {
        $out .= '<option value="' . $v . '"' . ($v === $selected ? ' selected' : '') . '>' . $label . '</option>';
    }
    return $out;
}

admin_head('Ads', 'ads', $current_user);
?>

<p class="muted">Disabled or empty slots render as an HTML comment only — never a visible placeholder.</p>

<form method="post" action="ads.php">
<?php echo csrf_field(); ?>
<?php foreach ($slots as $slot => $label): ?>
  <?php $ad = $ads[$slot] ?? ['enabled' => 0, 'code' => '', 'device' => 'both', 'start_date' => '', 'end_date' => '']; ?>
  <fieldset class="ad-slot">
    <legend><?php echo esc($label); ?> <span class="muted">(<?php echo esc($slot); ?>)</span></legend>
    <label class="checkbox-row">
      <input type="checkbox" name="ads[<?php echo esc($slot); ?>][enabled]" value="1"<?php echo (int) $ad['enabled'] ? ' checked' : ''; ?>>
      Enabled
    </label>
    <div class="field">
      <label>Ad code (HTML/JavaScript)</label>
      <textarea name="ads[<?php echo esc($slot); ?>][code]" class="tall" style="min-height:110px"><?php echo esc((string) $ad['code']); ?></textarea>
      <div class="hint">Pasted as-is. Only admins can edit this — treat it as trusted, but keep it clean.</div>
    </div>
    <div class="form-row-3">
      <div class="field">
        <label>Device</label>
        <select name="ads[<?php echo esc($slot); ?>][device]"><?php echo device_options((string) $ad['device']); ?></select>
      </div>
      <div class="field">
        <label>Start date</label>
        <input type="date" name="ads[<?php echo esc($slot); ?>][start_date]" value="<?php echo esc((string) $ad['start_date']); ?>">
      </div>
      <div class="field">
        <label>End date</label>
        <input type="date" name="ads[<?php echo esc($slot); ?>][end_date]" value="<?php echo esc((string) $ad['end_date']); ?>">
      </div>
    </div>
  </fieldset>
<?php endforeach; ?>
<button type="submit" class="btn btn-primary">Save ad settings</button>
</form>

<?php admin_foot(); ?>
