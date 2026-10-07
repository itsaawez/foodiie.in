<?php
/**
 * FOODIIE — site footer partial.
 * Vars: $site, $nav_categories (rows: 'name' + 'url').
 */
$brand = $site['name'] ?? 'FOODIIE';
$tagline = $site['tagline'] ?? 'Discover. Cook. Eat Better.';
$socials = [
    'facebook_url'  => ['label' => 'Facebook',  'icon' => '<path d="M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.9.3-1.5 1.6-1.5h1.3V4.9c-.6-.1-1.4-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8V11H8v3h2.5v7h3z"/>'],
    'instagram_url' => ['label' => 'Instagram', 'icon' => '<path d="M12 8.8A3.2 3.2 0 1 0 12 15.2 3.2 3.2 0 0 0 12 8.8zm0-2.1a5.3 5.3 0 1 1 0 10.6 5.3 5.3 0 0 1 0-10.6zm6.9-.3a1.2 1.2 0 1 1-2.4 0 1.2 1.2 0 0 1 2.4 0zM12 4.2c-2.5 0-2.9 0-3.9.1a5.2 5.2 0 0 0-1.7.3 3.5 3.5 0 0 0-2 2 5.2 5.2 0 0 0-.3 1.7c-.1 1-.1 1.4-.1 3.9s0 2.9.1 3.9a5.2 5.2 0 0 0 .3 1.7 3.5 3.5 0 0 0 2 2 5.2 5.2 0 0 0 1.7.3c1 .1 1.4.1 3.9.1s2.9 0 3.9-.1a5.2 5.2 0 0 0 1.7-.3 3.5 3.5 0 0 0 2-2 5.2 5.2 0 0 0 .3-1.7c.1-1 .1-1.4.1-3.9s0-2.9-.1-3.9a5.2 5.2 0 0 0-.3-1.7 3.5 3.5 0 0 0-2-2 5.2 5.2 0 0 0-1.7-.3c-1-.1-1.4-.1-3.9-.1z"/>'],
    'youtube_url'   => ['label' => 'YouTube',   'icon' => '<path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8c1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15.2V8.8L15.5 12 10 15.2z"/>'],
    'x_url'         => ['label' => 'X',         'icon' => '<path d="M17.8 3h3.1l-6.8 7.8L22 21h-6.3l-4.9-6.4L5.2 21H2.1l7.3-8.3L2 3h6.4l4.4 5.9L17.8 3zm-1.1 16.1h1.7L7.4 4.7H5.6l11.1 14.4z"/>'],
];
?>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-col footer-brand">
      <a class="brand" href="<?= esc(u('/')) ?>" aria-label="<?= esc($brand) ?> — home">
        <img class="brand-logo" src="<?= esc(u('assets/icons/logo.svg')) ?>" alt="" width="36" height="36" loading="lazy" decoding="async">
        <span class="brand-name" style="color:#fff"><?= esc($brand) ?></span>
      </a>
      <p><?= esc($tagline) ?></p>
      <div class="social-links">
        <?php foreach ($socials as $key => $s):
            $href = trim(setting($key, ''));
            if ($href === '') continue; ?>
        <a href="<?= esc($href) ?>" target="_blank" rel="noopener" aria-label="<?= esc($brand) ?> on <?= esc($s['label']) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><?= $s['icon'] ?></svg>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <nav class="footer-col" aria-label="Explore">
      <h2>Explore</h2>
      <ul>
        <li><a href="<?= esc(u('recipes/')) ?>">Recipes</a></li>
        <li><a href="<?= esc(u('healthy-food/')) ?>">Healthy Food</a></li>
        <li><a href="<?= esc(u('food-facts/')) ?>">Food Facts</a></li>
        <li><a href="<?= esc(u('food-tips/')) ?>">Food Tips</a></li>
        <li><a href="<?= esc(u('videos/')) ?>">Videos</a></li>
      </ul>
    </nav>
    <nav class="footer-col" aria-label="Company">
      <h2>Company</h2>
      <ul>
        <li><a href="<?= esc(u('about/')) ?>">About</a></li>
        <li><a href="<?= esc(u('contact/')) ?>">Contact</a></li>
        <li><a href="<?= esc(u('advertising/')) ?>">Advertising</a></li>
      </ul>
    </nav>
    <nav class="footer-col" aria-label="Legal">
      <h2>Legal</h2>
      <ul>
        <li><a href="<?= esc(u('privacy-policy/')) ?>">Privacy Policy</a></li>
        <li><a href="<?= esc(u('terms/')) ?>">Terms of Use</a></li>
        <li><a href="<?= esc(u('disclaimer/')) ?>">Disclaimer</a></li>
        <li><a href="<?= esc(u('cookie-policy/')) ?>">Cookie Policy</a></li>
      </ul>
    </nav>
  </div>
  <div class="footer-bottom">
    <div class="container footer-bottom-inner">
      <p>&copy; <?= date('Y') ?> <?= esc($brand) ?>. All rights reserved.</p>
      <p><a href="<?= esc(u('sitemap.xml')) ?>">Sitemap</a></p>
    </div>
  </div>
</footer>
<?php if (setting('cookie_consent_enabled', '') === '1'): ?>
<div class="cookie-banner" id="cookie-banner" role="dialog" aria-label="Cookie consent" hidden>
  <p>We use cookies to make <?= esc($brand) ?> better for you. <a href="<?= esc(u('cookie-policy/')) ?>">Cookie Policy</a></p>
  <button type="button" class="btn btn-primary btn-sm" data-accept-cookies>Accept</button>
</div>
<?php endif; ?>
