<?php
/**
 * FOODIIE — mega menu desktop & mobile partial.
 * Vars: $mega_menu, $site
 */
$mega = $mega_menu ?? [];

// Helper to partition cuisines into Indian, World, and Fusion
$all_cuisines = $mega['cuisine'] ?? [];
$indian_cuisines = [];
$world_cuisines = [];
$fusion_cuisines = [];

$world_slugs = ['chinese', 'italian', 'thai', 'mexican', 'mediterranean', 'french', 'american', 'asian', 'greek'];
$fusion_slugs = ['indo-chinese', 'fusion', 'continental'];

foreach ($all_cuisines as $c) {
    $s = strtolower($c['slug']);
    if (in_array($s, $fusion_slugs, true)) {
        $fusion_cuisines[] = $c;
    } elseif (in_array($s, $world_slugs, true)) {
        $world_cuisines[] = $c;
    } else {
        $indian_cuisines[] = $c;
    }
}

// Courses partition: Meals, Snacks & Starters, Desserts
$all_courses = $mega['course'] ?? [];
$meals = [];
$snacks = [];
$desserts = $mega['dessert'] ?? [];

$snack_slugs = ['snacks', 'appetizers', 'soups', 'salads', 'sandwiches', 'dips', 'kids-food', 'party-snacks'];

foreach ($all_courses as $c) {
    $s = strtolower($c['slug']);
    if (in_array($s, $snack_slugs, true)) {
        $snacks[] = $c;
    } else {
        $meals[] = $c;
    }
}

$ingredients = $mega['ingredient'] ?? [];
$styles = $mega['style'] ?? [];
$diets = $mega['diet'] ?? [];

$features = [
    ['name' => 'Food News', 'slug' => 'food-news', 'desc' => 'Latest food buzz & updates'],
    ['name' => 'Kitchen Hacks', 'slug' => 'kitchen-hacks', 'desc' => 'Quick tricks for smart cooking'],
    ['name' => 'Health & Wellness', 'slug' => 'health', 'desc' => 'Nutrition tips & healthy diets'],
    ['name' => 'Food Facts', 'slug' => 'food-facts', 'desc' => 'Fascinating food history & facts'],
    ['name' => 'Cooking Tips', 'slug' => 'food-tips', 'desc' => 'Master kitchen fundamentals'],
    ['name' => 'Trending Stories', 'slug' => 'trending', 'desc' => 'Viral recipes & popular dishes'],
];
?>

<!-- Desktop Mega Menu Navigation -->
<nav class="site-nav" id="site-nav" aria-label="Primary navigation">
  <ul class="nav-list">
    <li><a class="nav-link" href="<?= esc(u('/')) ?>">Home</a></li>

    <!-- Recipes Mega Menu -->
    <li class="nav-item-dropdown" data-dropdown="recipes">
      <a class="nav-link" href="<?= esc(u('recipes/')) ?>" aria-haspopup="true" aria-expanded="false">
        Recipes <span class="nav-arrow" aria-hidden="true">&#9662;</span>
      </a>
      <div class="nav-dropdown mega-dropdown-wide">
        <div class="mega-grid-2">
          <div class="mega-col">
            <h3 class="mega-col-title">By Ingredient</h3>
            <ul class="mega-links">
              <?php foreach (array_slice($ingredients, 0, 9) as $item): ?>
                <li><a href="<?= esc(u('recipes/ingredient/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="mega-col">
            <h3 class="mega-col-title">By Cooking Style</h3>
            <ul class="mega-links">
              <?php foreach (array_slice($styles, 0, 9) as $item): ?>
                <li><a href="<?= esc(u('style/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <div class="mega-dropdown-footer">
          <a href="<?= esc(u('recipes/')) ?>" class="mega-all-link">Browse All Recipes &rarr;</a>
        </div>
      </div>
    </li>

    <!-- Cuisines Mega Menu -->
    <li class="nav-item-dropdown" data-dropdown="cuisines">
      <a class="nav-link" href="<?= esc(u('recipes/')) ?>" aria-haspopup="true" aria-expanded="false">
        Cuisines <span class="nav-arrow" aria-hidden="true">&#9662;</span>
      </a>
      <div class="nav-dropdown mega-dropdown-wide">
        <div class="mega-grid-3">
          <div class="mega-col">
            <h3 class="mega-col-title">Indian Regional</h3>
            <ul class="mega-links">
              <?php foreach (array_slice($indian_cuisines, 0, 10) as $item): ?>
                <li><a href="<?= esc(u('cuisine/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="mega-col">
            <h3 class="mega-col-title">World Cuisines</h3>
            <ul class="mega-links">
              <?php foreach (array_slice($world_cuisines, 0, 9) as $item): ?>
                <li><a href="<?= esc(u('cuisine/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="mega-col">
            <h3 class="mega-col-title">Fusion &amp; Modern</h3>
            <ul class="mega-links">
              <?php foreach ($fusion_cuisines as $item): ?>
                <li><a href="<?= esc(u('cuisine/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
              <li><a href="<?= esc(u('cuisine/jain/')) ?>">Jain Recipes</a></li>
              <li><a href="<?= esc(u('cuisine/satvik/')) ?>">Satvik Food</a></li>
            </ul>
          </div>
        </div>
      </div>
    </li>

    <!-- Courses Mega Menu -->
    <li class="nav-item-dropdown" data-dropdown="courses">
      <a class="nav-link" href="<?= esc(u('recipes/')) ?>" aria-haspopup="true" aria-expanded="false">
        Courses <span class="nav-arrow" aria-hidden="true">&#9662;</span>
      </a>
      <div class="nav-dropdown mega-dropdown-wide">
        <div class="mega-grid-3">
          <div class="mega-col">
            <h3 class="mega-col-title">Daily Meals</h3>
            <ul class="mega-links">
              <?php foreach ($meals as $item): ?>
                <li><a href="<?= esc(u('course/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="mega-col">
            <h3 class="mega-col-title">Snacks &amp; Sides</h3>
            <ul class="mega-links">
              <?php foreach (array_slice($snacks, 0, 7) as $item): ?>
                <li><a href="<?= esc(u('course/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="mega-col">
            <h3 class="mega-col-title">Desserts &amp; Bakes</h3>
            <ul class="mega-links">
              <?php foreach (array_slice($desserts, 0, 7) as $item): ?>
                <li><a href="<?= esc(u('dessert/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </li>

    <!-- Health Dropdown -->
    <li class="nav-item-dropdown" data-dropdown="health">
      <a class="nav-link" href="<?= esc(u('health/')) ?>" aria-haspopup="true" aria-expanded="false">
        Health <span class="nav-arrow" aria-hidden="true">&#9662;</span>
      </a>
      <div class="nav-dropdown" style="min-width:260px;">
        <h3 class="mega-col-title">Diet &amp; Nutrition</h3>
        <ul class="mega-links">
          <?php foreach ($diets as $item): ?>
            <li><a href="<?= esc(u('diet/' . $item['slug'] . '/')) ?>"><?= esc($item['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </li>

    <!-- Features Dropdown -->
    <li class="nav-item-dropdown" data-dropdown="features">
      <a class="nav-link" href="<?= esc(u('articles/')) ?>" aria-haspopup="true" aria-expanded="false">
        Features <span class="nav-arrow" aria-hidden="true">&#9662;</span>
      </a>
      <div class="nav-dropdown" style="min-width:280px;">
        <h3 class="mega-col-title">Food Stories &amp; Tips</h3>
        <ul class="mega-links">
          <?php foreach ($features as $f): ?>
            <li>
              <a href="<?= esc(u($f['slug'] . '/')) ?>" style="padding:.4rem .5rem;">
                <strong style="display:block;color:var(--dark);"><?= esc($f['name']) ?></strong>
                <span class="muted" style="font-size:.78rem;"><?= esc($f['desc']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </li>

    <!-- Videos -->
    <li><a class="nav-link" href="<?= esc(u('videos/')) ?>">Videos</a></li>

    <!-- Shorts -->
    <li>
      <a class="nav-link" href="<?= esc(u('shorts/')) ?>">
        Shorts
        <span class="nav-badge-short">⚡ New</span>
      </a>
    </li>
  </ul>
</nav>
