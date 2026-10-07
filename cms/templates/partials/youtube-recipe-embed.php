<?php
/**
 * FOODIIE — YouTube Recipe Video Component
 *
 * Official privacy-enhanced YouTube embed player with click-to-play performance facade,
 * support for standard 16:9 recipes and 9:16 YouTube Shorts, creator attribution,
 * and graceful fallback.
 *
 * Vars: $recipe (array)
 */

$recipe = $recipe ?? [];
$vid = trim((string) ($recipe['youtube_video_id'] ?? ''));
$enabled = (string) ($recipe['youtube_enabled'] ?? '1') !== '0';

// If no valid video or video is disabled, output nothing (no empty container).
if ($vid === '' || !$enabled) {
    return;
}

$video_url = trim((string) ($recipe['youtube_video_url'] ?? ''));
$video_title = trim((string) ($recipe['youtube_video_title'] ?? ''));
$video_desc = trim((string) ($recipe['youtube_video_description'] ?? ''));
$channel_name = trim((string) ($recipe['youtube_channel_name'] ?? ''));
$video_type = (string) ($recipe['youtube_video_type'] ?? 'normal');
if ($video_type !== 'shorts') {
    $video_type = 'normal';
}

$is_shorts = ($video_type === 'shorts');
$embed_src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($vid) . '?rel=0';
$watch_url = $is_shorts
    ? 'https://www.youtube.com/shorts/' . rawurlencode($vid)
    : 'https://www.youtube.com/watch?v=' . rawurlencode($vid);

$thumb_url = 'https://img.youtube.com/vi/' . rawurlencode($vid) . '/hqdefault.jpg';
$display_title = $video_title !== '' ? $video_title : ($recipe['name'] ?? 'Recipe Video');
?>
<section class="recipe-section recipe-video-section" id="recipe-video" aria-labelledby="recipe-video-heading">
  <div class="recipe-video-card <?= $is_shorts ? 'recipe-video-card--shorts' : '' ?>">
    <!-- Header / Label -->
    <div class="recipe-video-header">
      <div class="recipe-video-badge">
        <svg class="recipe-video-badge-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
          <path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/>
        </svg>
        <span id="recipe-video-heading">WATCH THE RECIPE</span>
      </div>
      <?php if ($channel_name !== ''): ?>
        <span class="recipe-video-author">Video by <strong><?= esc($channel_name) ?></strong></span>
      <?php endif; ?>
    </div>

    <?php if ($video_title !== ''): ?>
      <h3 class="recipe-video-title"><?= esc($video_title) ?></h3>
    <?php endif; ?>

    <?php if ($video_desc !== ''): ?>
      <p class="recipe-video-desc"><?= esc($video_desc) ?></p>
    <?php endif; ?>

    <!-- Player / Performance Click-to-Play Facade -->
    <div class="recipe-video-player-wrap <?= $is_shorts ? 'recipe-video-player-wrap--shorts' : '' ?>"
         data-youtube-embed
         data-video-id="<?= esc($vid) ?>"
         data-embed-src="<?= esc($embed_src) ?>"
         data-title="<?= esc($display_title) ?>">

      <div class="recipe-video-facade" role="button" tabindex="0" aria-label="Play <?= esc($display_title) ?>">
        <img class="recipe-video-thumbnail"
             src="<?= esc($thumb_url) ?>"
             alt="Thumbnail for <?= esc($display_title) ?>"
             loading="lazy"
             decoding="async"
             width="480"
             height="360"
             onerror="this.onerror=null;this.src='https://img.youtube.com/vi/<?= esc($vid) ?>/0.jpg';">
        <div class="recipe-video-play-btn" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
            <polygon points="5 3 19 12 5 21 5 3"></polygon>
          </svg>
        </div>
        <span class="recipe-video-hint">Click to play video</span>
      </div>

      <noscript>
        <iframe
          class="recipe-video-iframe"
          src="<?= esc($embed_src) ?>"
          title="<?= esc($display_title) ?>"
          loading="lazy"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          referrerpolicy="strict-origin-when-cross-origin"
          allowfullscreen>
        </iframe>
      </noscript>
    </div>

    <!-- Attribution & External Link -->
    <div class="recipe-video-footer">
      <a class="recipe-video-attribution-link"
         href="<?= esc($watch_url) ?>"
         target="_blank"
         rel="noopener noreferrer"
         data-action="youtube-video-click"
         data-video-id="<?= esc($vid) ?>">
        <span>Watch this recipe on YouTube</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </a>

      <!-- Fallback notification -->
      <p class="recipe-video-fallback-text">
        Video unavailable? You can still follow the full written recipe below.
      </p>
    </div>
  </div>
</section>
