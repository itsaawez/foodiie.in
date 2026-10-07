<?php
/**
 * FOODIIE — video template.
 * Vars: $site,$page,$jsonld,$video,$category_name,$related.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => $jsonld ?? [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);

$video = $video ?? [];
$category_name = (string) ($category_name ?? '');
$vid = (string) ($video['video_id'] ?? '');
$thumb = video_thumbnail_url($video);
if ($thumb === '') $thumb = u('assets/images/placeholder.jpg');
$pub = fmt_date($video['publish_at'] ?? $video['created_at'] ?? null);
$duration = trim((string) ($video['duration'] ?? ''));
$share_url = abs_url(ltrim((string) ($page['canonical'] ?? '/'), '/'));
?>
<main id="main-content">
  <div class="container article-wrap">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= esc(u('/')) ?>">Home</a></li>
        <li><a href="<?= esc(u('videos/')) ?>">Videos</a></li>
        <li aria-current="page"><?= esc($video['title'] ?? '') ?></li>
      </ol>
    </nav>

    <article>
      <header class="article-header">
        <?php if ($category_name !== ''): ?>
        <p><span class="card-tag"><?= esc($category_name) ?></span></p>
        <?php endif; ?>
        <h1><?= esc($video['title'] ?? '') ?></h1>
        <p class="byline">
          <?php if ($pub !== ''): ?>Published <?= esc($pub) ?><?php endif; ?>
          <?php if ($duration !== ''): ?> &middot; <?= esc($duration) ?><?php endif; ?>
        </p>
      </header>

      <div class="yt-facade" data-id="<?= esc($vid) ?>" role="button" tabindex="0" aria-label="Play video: <?= esc($video['title'] ?? '') ?>">
        <img src="<?= esc($thumb) ?>" alt="<?= esc($video['title'] ?? '') ?>" loading="eager" decoding="async" fetchpriority="high" width="820" height="461">
        <span class="yt-play" aria-hidden="true"></span>
      </div>

      <?= ad_html('article_top') ?>

      <?php if (trim((string) ($video['description'] ?? '')) !== ''): ?>
      <p class="video-desc"><?= esc($video['description']) ?></p>
      <?php endif; ?>

      <?php partial('share', ['share_url' => $share_url, 'share_title' => (string) ($video['title'] ?? '')]); ?>

      <?= ad_html('article_bottom') ?>
    </article>

    <?php if (!empty($related)): ?>
    <section class="related" aria-labelledby="related-title">
      <h2 id="related-title">More videos</h2>
      <div class="card-grid">
        <?php foreach ($related as $item) partial('card', ['item' => $item]); ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>
<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
