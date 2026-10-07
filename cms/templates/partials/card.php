<?php
/**
 * FOODIIE — generic content card.
 * Vars: $item = ['type','title','url','image','category','description','date','meta'].
 */
$item = $item ?? [];
$title = (string) ($item['title'] ?? '');
$href = fd_public_url((string) ($item['url'] ?? ''));
$img = trim((string) ($item['image'] ?? ''));
if ($img === '') {
    $img = u('assets/images/placeholder.jpg');
} elseif (!preg_match('#^(https?://)#i', $img)) {
    $img = fd_public_url($img);
}
$category = trim((string) ($item['category'] ?? ''));
$description = trim((string) ($item['description'] ?? ''));
$date = trim((string) ($item['date'] ?? ''));
$meta = trim((string) ($item['meta'] ?? ''));
$meta_line = trim($date . ($date !== '' && $meta !== '' ? ' · ' : '') . $meta, ' ·');
$has_video = !empty($item['has_video']);
?>
<article class="card">
  <a class="card-media" href="<?= esc($href) ?>" tabindex="-1" aria-hidden="true">
    <img src="<?= esc($img) ?>" alt="<?= esc($title) ?>" loading="lazy" decoding="async" width="400" height="250">
    <?php if ($has_video): ?>
      <span class="card-video-badge" aria-label="Includes video recipe"><span class="card-video-icon" aria-hidden="true">▶</span> Video Recipe</span>
    <?php endif; ?>
  </a>
  <div class="card-body">
    <?php if ($category !== ''): ?>
    <span class="card-tag"><?= esc($category) ?></span>
    <?php endif; ?>
    <h3 class="card-title"><a href="<?= esc($href) ?>"><?= esc($title) ?></a></h3>
    <?php if ($description !== ''): ?>
    <p class="card-desc"><?= esc($description) ?></p>
    <?php endif; ?>
    <?php if ($meta_line !== ''): ?>
    <p class="card-meta"><?= esc($meta_line) ?></p>
    <?php endif; ?>
  </div>
</article>
