<?php
/**
 * FOODIIE — YouTube Shorts grid template.
 * Vars: $site, $page, $jsonld, $shorts, $nav_categories
 */
partial('head', ['page' => $page ?? [], 'jsonld' => $jsonld ?? [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);

$shorts = $shorts ?? [];
$count = count($shorts);
?>
<main id="main-content">
  <div class="container">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= esc(u('/')) ?>">Home</a></li>
        <li aria-current="page">Shorts</li>
      </ol>
    </nav>

    <header class="article-header" style="margin-top:.5rem">
      <h1>Food Shorts</h1>
      <p class="recipe-lede">Quick 60-second recipe hacks, kitchen tips, and street food highlights.</p>
      <p class="byline"><?= $count ?> <?= $count === 1 ? 'short' : 'shorts' ?></p>
    </header>

    <?= ad_html('header') ?>

    <?php if ($shorts): ?>
    <div class="shorts-grid">
      <?php foreach ($shorts as $s): ?>
        <?php
          $vid = (string) ($s['video_id'] ?? '');
          $title = (string) ($s['title'] ?? '');
          $thumb = !empty($s['thumbnail']) ? $s['thumbnail'] : ($vid ? "https://img.youtube.com/vi/{$vid}/hqdefault.jpg" : '');
          $watch_url = $vid ? "https://www.youtube.com/shorts/{$vid}" : '#';
        ?>
        <div class="short-card" data-video-id="<?= esc($vid) ?>" data-title="<?= esc($title) ?>" role="button" tabindex="0" aria-label="Play <?= esc($title) ?>">
          <span class="short-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 15l5.19-3L10 9v6m11.56-7.83c.13.47.22 1.1.28 1.9.07.8.1 1.49.1 2.09L22 12c0 2.19-.16 3.8-.44 4.83-.25.9-.83 1.48-1.73 1.73-.47.13-1.33.22-2.65.28-1.3.07-2.49.1-3.59.1L12 22c-4.19 0-6.8-.16-7.83-.44-.9-.25-1.48-.83-1.73-1.73-.13-.47-.22-1.1-.28-1.9-.07-.8-.1-1.49-.1-2.09L2 12c0-2.19.16-3.8.44-4.83.25-.9.83-1.48 1.73-1.73.47-.13 1.33-.22 2.65-.28 1.3-.07 2.49-.1 3.59-.1L12 2c4.19 0 6.8.16 7.83.44.9.25 1.48.83 1.73 1.73z"/></svg>
            Short
          </span>
          <img src="<?= esc($thumb) ?>" alt="<?= esc($title) ?>" loading="lazy" decoding="async" width="220" height="390">
          <div class="short-play" aria-hidden="true">&#9658;</div>
          <div class="short-overlay">
            <h2 class="short-title"><?= esc($title) ?></h2>
            <?php if (!empty($s['description'])): ?>
              <p class="short-meta"><?= esc(excerpt($s['description'], 60)) ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:3rem 1rem;">
      <p class="muted">No shorts available yet &mdash; check back soon!</p>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- Interactive Shorts Modal Player -->
<div class="short-modal" id="shortModal" aria-hidden="true" role="dialog" aria-label="Video Player">
  <div class="short-modal-inner">
    <button type="button" class="short-modal-close" id="modalClose" aria-label="Close video">&times;</button>
    <iframe id="modalFrame" src="about:blank" style="width:100%;height:100%;border:0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
  </div>
</div>

<script>
(function() {
  var modal = document.getElementById('shortModal');
  var frame = document.getElementById('modalFrame');
  var closeBtn = document.getElementById('modalClose');

  function openShort(vid) {
    if (!vid) return;
    frame.src = 'https://www.youtube.com/embed/' + encodeURIComponent(vid) + '?autoplay=1&rel=0';
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeShort() {
    frame.src = 'about:blank';
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.short-card').forEach(function(card) {
    card.addEventListener('click', function() {
      openShort(this.getAttribute('data-video-id'));
    });
    card.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openShort(this.getAttribute('data-video-id'));
      }
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', closeShort);
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeShort();
    });
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
      closeShort();
    }
  });
})();
</script>

<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
