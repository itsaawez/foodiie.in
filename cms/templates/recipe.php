<?php
/**
 * FOODIIE — Times Food inspired Recipe detail template.
 * Vars: $site, $page, $jsonld, $recipe, $related.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => $jsonld ?? [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);

$recipe = $recipe ?? [];
$img = trim((string) ($recipe['image'] ?? ''));
$img = $img !== '' ? public_image_url($img) : u('assets/images/placeholder.jpg');

// Dietary classification
$diet = strtolower(trim((string) ($recipe['diet_type'] ?? '')));
$diet_class = 'diet-veg';
$diet_label = 'Vegetarian';
if (strpos($diet, 'non') !== false || $diet === 'non-veg') {
    $diet_class = 'diet-non-veg';
    $diet_label = 'Non-Veg';
} elseif (strpos($diet, 'vegan') !== false) {
    $diet_class = 'diet-vegan';
    $diet_label = 'Vegan';
} elseif (strpos($diet, 'egg') !== false) {
    $diet_class = 'diet-non-veg';
    $diet_label = 'Contains Egg';
}

$cuisine = trim((string) ($recipe['cuisine'] ?? ''));
$cuisine_slug = $cuisine !== '' ? slugify($cuisine) : '';

$course = trim((string) ($recipe['course'] ?? ''));
$course_slug = $course !== '' ? slugify($course) : '';

$method = trim((string) ($recipe['cooking_method'] ?? ''));
$method_slug = $method !== '' ? slugify($method) : '';

$skill = trim((string) ($recipe['skill_level'] ?? $recipe['difficulty'] ?? ''));

// Meta highlights
$prep_time  = trim((string) ($recipe['prep_time'] ?? ''));
$cook_time  = trim((string) ($recipe['cook_time'] ?? ''));
$total_time = trim((string) ($recipe['total_time'] ?? ''));
$servings   = trim((string) ($recipe['servings'] ?? ''));
$difficulty = trim((string) ($recipe['difficulty'] ?? 'Easy'));
$calories   = trim((string) ($recipe['calories'] ?? ''));

// Rating calculations
$rating_sum   = (float) ($recipe['rating_sum'] ?? 24);
$rating_count = (int) ($recipe['rating_count'] ?? 5);
$avg_rating   = $rating_count > 0 ? round($rating_sum / $rating_count, 1) : 4.8;
if ($avg_rating <= 0 || $avg_rating > 5) {
    $avg_rating = 4.8;
}

// Ingredients parser
$ingredients = json_decode((string) ($recipe['ingredients'] ?? ''), true);
if (!is_array($ingredients)) {
    $ingredients = array_filter(array_map('trim', explode("\n", (string) ($recipe['ingredients'] ?? ''))));
}
$ingredients = array_values(array_filter(array_map(function($v) { return trim((string) $v); }, (array) $ingredients)));

// Instructions parser
$instructions = json_decode((string) ($recipe['instructions'] ?? ''), true);
if (!is_array($instructions)) {
    $instructions = array_filter(array_map('trim', explode("\n", (string) ($recipe['instructions'] ?? ''))));
}
$instructions = array_values(array_filter(array_map(function($v) { return trim((string) $v); }, (array) $instructions)));

// Nutrition rows
$nutrition_rows = [
    'Calories' => $calories,
    'Protein'  => trim((string) ($recipe['protein'] ?? '')),
    'Carbs'    => trim((string) ($recipe['carbs'] ?? '')),
    'Fat'      => trim((string) ($recipe['fat'] ?? '')),
];
$nutrition_rows = array_filter($nutrition_rows, function($v) { return $v !== ''; });

$tags = parse_tags((string) ($recipe['tags'] ?? ''));
$share_url = abs_url(ltrim((string) ($page['canonical'] ?? '/'), '/'));
$slug = (string) ($recipe['slug'] ?? '');
$video_pos = trim((string) ($recipe['youtube_position'] ?? 'after_intro'));
if (!in_array($video_pos, ['after_intro', 'after_ingredients', 'after_instructions', 'before_related'], true)) {
    $video_pos = 'after_intro';
}
?>
<main id="main-content">
  <div class="container recipe-wrap">
    <!-- Breadcrumb Trail -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= esc(u('/')) ?>">Home</a></li>
        <li><a href="<?= esc(u('recipes/')) ?>">Recipes</a></li>
        <?php if ($cuisine !== ''): ?>
          <li><a href="<?= esc(u('cuisine/' . $cuisine_slug . '/')) ?>"><?= esc($cuisine) ?></a></li>
        <?php elseif ($course !== ''): ?>
          <li><a href="<?= esc(u('course/' . $course_slug . '/')) ?>"><?= esc($course) ?></a></li>
        <?php endif; ?>
        <li aria-current="page"><?= esc($recipe['name'] ?? '') ?></li>
      </ol>
    </nav>

    <article class="recipe-article" data-recipe-slug="<?= esc($slug) ?>">
      <!-- Header -->
      <header class="recipe-header">
        <div class="recipe-badges">
          <?php if ($diet !== ''): ?>
            <span class="recipe-badge-diet <?= esc($diet_class) ?>">
              <span class="diet-dot" aria-hidden="true"></span>
              <?= esc($diet_label) ?>
            </span>
          <?php endif; ?>

          <?php if ($cuisine !== ''): ?>
            <a class="recipe-badge-pill" href="<?= esc(u('cuisine/' . $cuisine_slug . '/')) ?>">
              <span>🌍</span> <?= esc($cuisine) ?>
            </a>
          <?php endif; ?>

          <?php if ($course !== ''): ?>
            <a class="recipe-badge-pill" href="<?= esc(u('course/' . $course_slug . '/')) ?>">
              <span>🍽️</span> <?= esc($course) ?>
            </a>
          <?php endif; ?>

          <?php if ($method !== ''): ?>
            <a class="recipe-badge-pill" href="<?= esc(u('style/' . $method_slug . '/')) ?>">
              <span>🔥</span> <?= esc($method) ?>
            </a>
          <?php endif; ?>

          <?php if ($skill !== ''): ?>
            <span class="recipe-badge-pill" style="background:#EEF2F6;color:#334155;">
              <span>⚡</span> <?= esc(ucfirst($skill)) ?>
            </span>
          <?php endif; ?>
        </div>

        <h1><?= esc($recipe['name'] ?? '') ?></h1>

        <div class="recipe-submeta">
          <!-- Star Rating Widget -->
          <div class="recipe-rating-widget" data-recipe-rating-widget data-slug="<?= esc($slug) ?>" data-current="<?= esc($avg_rating) ?>">
            <div class="recipe-rating-stars" aria-label="Rating: <?= esc($avg_rating) ?> out of 5 stars">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="star-icon <?= $i <= round($avg_rating) ? 'filled' : 'empty' ?>" data-star="<?= $i ?>" viewBox="0 0 24 24">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
              <?php endfor; ?>
            </div>
            <span class="recipe-rating-text" data-rating-label>
              <strong><?= esc($avg_rating) ?></strong>/5 (<?= (int)$rating_count ?> reviews)
            </span>
          </div>
        </div>
      </header>

      <!-- Action Bar: Print, Save, Jump, Share -->
      <aside class="recipe-action-bar" aria-label="Recipe actions">
        <div class="recipe-action-buttons">
          <button type="button" class="btn-recipe-action" data-action="print" title="Print this recipe card">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <polyline points="6 9 6 2 18 2 18 9"></polyline>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
              <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print
          </button>

          <button type="button" class="btn-recipe-action btn-save-recipe" data-action="save" data-slug="<?= esc($slug) ?>" data-title="<?= esc($recipe['name'] ?? '') ?>" data-url="<?= esc($share_url) ?>" title="Save to bookmarks">
            <svg class="heart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            <span class="save-btn-text">Save</span>
          </button>

          <a href="#ingredients-section" class="btn-recipe-action" title="Jump straight to ingredients and instructions">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <polyline points="19 12 12 19 5 12"></polyline>
            </svg>
            Jump to Recipe
          </a>
        </div>

        <?php partial('share', ['share_url' => $share_url, 'share_title' => (string) ($recipe['name'] ?? '')]); ?>
      </aside>

      <!-- Hero Image — LCP element: eager load, no lazy, explicit dimensions -->
      <figure class="recipe-hero">
        <img src="<?= esc($img) ?>"
             alt="<?= esc($recipe['image_alt'] ?? $recipe['name'] ?? '') ?>"
             loading="eager"
             decoding="async"
             fetchpriority="high"
             width="960"
             height="540">
      </figure>

      <?= ad_html('article_top') ?>

      <!-- Description / Lede -->
      <?php if (trim((string) ($recipe['description'] ?? '')) !== ''): ?>
        <p class="recipe-lede"><?= esc($recipe['description']) ?></p>
      <?php endif; ?>

      <!-- Quick Highlights Meta Cards -->
      <dl class="recipe-meta-cards" aria-label="Recipe summary stats">
        <?php if ($prep_time !== ''): ?>
          <div class="recipe-meta-card">
            <span class="recipe-meta-card-icon" aria-hidden="true">⏱️</span>
            <dt>Prep Time</dt>
            <dd><?= esc($prep_time) ?></dd>
          </div>
        <?php endif; ?>

        <?php if ($cook_time !== ''): ?>
          <div class="recipe-meta-card">
            <span class="recipe-meta-card-icon" aria-hidden="true">🔥</span>
            <dt>Cook Time</dt>
            <dd><?= esc($cook_time) ?></dd>
          </div>
        <?php endif; ?>

        <?php if ($total_time !== ''): ?>
          <div class="recipe-meta-card">
            <span class="recipe-meta-card-icon" aria-hidden="true">⏳</span>
            <dt>Total Time</dt>
            <dd><?= esc($total_time) ?></dd>
          </div>
        <?php endif; ?>

        <?php if ($servings !== ''): ?>
          <div class="recipe-meta-card">
            <span class="recipe-meta-card-icon" aria-hidden="true">🍽️</span>
            <dt>Servings</dt>
            <dd><?= esc($servings) ?></dd>
          </div>
        <?php endif; ?>

        <div class="recipe-meta-card">
          <span class="recipe-meta-card-icon" aria-hidden="true">⚡</span>
          <dt>Difficulty</dt>
          <dd><?= esc(ucfirst($difficulty)) ?></dd>
        </div>

        <?php if ($calories !== ''): ?>
          <div class="recipe-meta-card">
            <span class="recipe-meta-card-icon" aria-hidden="true">🥗</span>
            <dt>Calories</dt>
            <dd><?= esc($calories) ?></dd>
          </div>
        <?php endif; ?>
      </dl>

      <div id="recipe-content">
        <?php if ($video_pos === 'after_intro') partial('youtube-recipe-embed', ['recipe' => $recipe]); ?>

        <!-- Interactive Checkbox Ingredients -->
        <?php partial('ingredient-list', ['ingredients' => $ingredients, 'servings' => $servings]); ?>

        <?php if ($video_pos === 'after_ingredients') partial('youtube-recipe-embed', ['recipe' => $recipe]); ?>

        <!-- Step-by-Step Instructions -->
        <?php if ($instructions): ?>
          <section class="recipe-section" id="instructions-section" aria-labelledby="instructions-title">
            <div class="recipe-section-head">
              <div class="recipe-section-title-wrap">
                <h2 id="instructions-title" class="recipe-section-title">Step-by-Step Instructions</h2>
                <span class="recipe-section-badge"><?= count($instructions) ?> steps</span>
              </div>
            </div>

            <ol class="recipe-instructions-list">
              <?php foreach ($instructions as $step_idx => $step): ?>
                <li class="recipe-instruction-step">
                  <span class="recipe-step-num"><?= (int)($step_idx + 1) ?></span>
                  <div class="recipe-step-content">
                    <h3 class="recipe-step-title">Step <?= (int)($step_idx + 1) ?></h3>
                    <p class="recipe-step-text"><?= esc($step) ?></p>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          </section>
        <?php endif; ?>

        <?php if ($video_pos === 'after_instructions') partial('youtube-recipe-embed', ['recipe' => $recipe]); ?>

        <!-- Chef's Pro Tips -->
        <div class="recipe-chef-tips">
          <div class="chef-tips-icon" aria-hidden="true">💡</div>
          <div class="chef-tips-content">
            <h3>Chef's Pro Tip</h3>
            <p>For optimal flavor, ensure all fresh spices are freshly roasted or ground before adding. Adjust seasoning gradually according to taste and let the dish rest for 3–5 minutes before serving.</p>
          </div>
        </div>

        <?= ad_html('article_middle') ?>

        <!-- Rich Nutrition Card -->
        <?php partial('nutrition', ['recipe' => $recipe, 'nutrition_rows' => $nutrition_rows]); ?>

        <!-- Tags & Categories -->
        <?php if ($tags): ?>
          <div class="recipe-section" style="margin-top:1.5rem;">
            <h3 style="font-size:1.05rem;margin-bottom:.65rem;color:var(--dark);">Tags &amp; Keywords</h3>
            <ul class="tags" aria-label="Tags">
              <?php foreach ($tags as $t): ?>
                <li class="tag"><a href="<?= esc(u('search/?q=' . urlencode($t))) ?>" style="color:inherit;text-decoration:none;">#<?= esc($t) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?= ad_html('article_bottom') ?>

        <?php if ($video_pos === 'before_related') partial('youtube-recipe-embed', ['recipe' => $recipe]); ?>
      </div>
    </article>

    <!-- Related Recipes -->
    <?php if (!empty($related)): ?>
      <section class="related" aria-labelledby="related-title">
        <h2 id="related-title">More Recipes You'll Love</h2>
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
