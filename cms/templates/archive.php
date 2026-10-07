<?php
/**
 * FOODIIE — archive (category listing) template.
 * Vars: $site,$page,$jsonld,$category,$items,$item_count.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => $jsonld ?? [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);

$category = $category ?? [];
$items = $items ?? [];
$count = (int) ($item_count ?? count($items));
?>
<main id="main-content">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= esc(u('/')) ?>">Home</a></li>
        <?php if (!empty($breadcrumb_trail)): ?>
          <?php foreach ($breadcrumb_trail as $bc): ?>
            <?php if (!empty($bc['url'])): ?>
              <li><a href="<?= esc(u($bc['url'])) ?>"><?= esc($bc['name']) ?></a></li>
            <?php else: ?>
              <li aria-current="page"><?= esc($bc['name']) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <li aria-current="page"><?= esc($category['name'] ?? '') ?></li>
        <?php endif; ?>
      </ol>
    </nav>

    <header class="article-header" style="margin-top:.5rem">
      <h1><?= esc($category['name'] ?? '') ?></h1>
      <?php if (trim((string) ($category['description'] ?? '')) !== ''): ?>
      <p class="recipe-lede"><?= esc($category['description']) ?></p>
      <?php endif; ?>
      <p class="byline"><?= $count ?> <?= $count === 1 ? 'item' : 'items' ?></p>
    </header>

    <?= ad_html('header') ?>

    <?php if ($items): ?>
    <div class="card-grid section" style="padding-top:1rem">
      <?php foreach ($items as $item) partial('card', ['item' => $item]); ?>
    </div>
    <?php else: ?>
    <p class="muted">Nothing here yet &mdash; check back soon for fresh content.</p>
    <?php endif; ?>
  </div>
</main>
<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
