<?php
/** FOODIIE admin — site SEO + tracking settings. */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$pdo = db();

$fields = [
    'site_name' => ['Site name', 'text', ''],
    'site_tagline' => ['Site tagline', 'text', ''],
    'default_author' => ['Default author', 'text', 'Used when a piece has no author assigned.'],
    'google_analytics_id' => ['Google Analytics ID', 'text', 'e.g. G-XXXXXXXXXX'],
    'ga_enabled' => ['Enable Google Analytics', 'checkbox', ''],
    'google_search_console_verification' => ['Google Search Console verification', 'text', 'The content value of the verification meta tag.'],
    'meta_pixel_id' => ['Meta Pixel ID', 'text', ''],
    'meta_pixel_enabled' => ['Enable Meta Pixel', 'checkbox', ''],
    'cookie_consent_enabled' => ['Enable cookie consent banner', 'checkbox', ''],
    'cookie_consent_text' => ['Cookie consent text', 'textarea', ''],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $posted = (isset($_POST['s']) && is_array($_POST['s'])) ? $_POST['s'] : [];
    foreach ($fields as $key => [$label, $type]) {
        if ($type === 'checkbox') {
            save_setting($key, !empty($posted[$key]) ? '1' : '0');
        } else {
            save_setting($key, trim((string) ($posted[$key] ?? '')));
        }
    }
    [$ok, $msg] = regen_site();
    flash($ok ? 'success' : 'warning', 'Settings saved. ' . $msg);
    redirect('seo.php');
}

admin_head('SEO & Site Settings', 'seo', $current_user);
?>

<div class="panel">
  <h2>Environment (read-only, from .env)</h2>
  <dl class="kv">
    <dt>APP_URL</dt><dd><code><?php echo esc(app_url()); ?></code></dd>
    <dt>BASE_PATH</dt><dd><code><?php echo esc(base_path() === '' ? '(empty — site at domain root)' : base_path()); ?></code></dd>
  </dl>
  <p class="hint">Change these in the project <code>.env</code> file, not here.</p>
</div>

<form method="post" action="seo.php">
<?php echo csrf_field(); ?>
<div class="panel">
  <h2>Site identity</h2>
  <?php foreach (['site_name', 'site_tagline', 'default_author'] as $key): ?>
    <div class="field">
      <label for="s-<?php echo esc($key); ?>"><?php echo esc($fields[$key][0]); ?></label>
      <input type="text" id="s-<?php echo esc($key); ?>" name="s[<?php echo esc($key); ?>]" value="<?php echo esc(setting($key)); ?>">
    </div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2>Analytics &amp; tracking</h2>
  <div class="field">
    <label for="s-ga">Google Analytics ID</label>
    <input type="text" id="s-ga" name="s[google_analytics_id]" value="<?php echo esc(setting('google_analytics_id')); ?>" placeholder="G-XXXXXXXXXX">
  </div>
  <label class="checkbox-row">
    <input type="checkbox" name="s[ga_enabled]" value="1"<?php echo setting('ga_enabled') === '1' ? ' checked' : ''; ?>>
    Enable Google Analytics
  </label>
  <div class="field">
    <label for="s-gsc">Google Search Console verification</label>
    <input type="text" id="s-gsc" name="s[google_search_console_verification]" value="<?php echo esc(setting('google_search_console_verification')); ?>">
    <div class="hint">The content value of the verification meta tag.</div>
  </div>
  <div class="field">
    <label for="s-pixel">Meta Pixel ID</label>
    <input type="text" id="s-pixel" name="s[meta_pixel_id]" value="<?php echo esc(setting('meta_pixel_id')); ?>">
  </div>
  <label class="checkbox-row">
    <input type="checkbox" name="s[meta_pixel_enabled]" value="1"<?php echo setting('meta_pixel_enabled') === '1' ? ' checked' : ''; ?>>
    Enable Meta Pixel
  </label>
</div>

<div class="panel">
  <h2>Cookie consent</h2>
  <label class="checkbox-row">
    <input type="checkbox" name="s[cookie_consent_enabled]" value="1"<?php echo setting('cookie_consent_enabled') === '1' ? ' checked' : ''; ?>>
    Enable cookie consent banner
  </label>
  <div class="field">
    <label for="s-consent-text">Cookie consent text</label>
    <textarea id="s-consent-text" name="s[cookie_consent_text]"><?php echo esc(setting('cookie_consent_text')); ?></textarea>
  </div>
</div>

<button type="submit" class="btn btn-primary">Save settings</button>
</form>

<?php admin_foot(); ?>
