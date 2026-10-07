<?php
/**
 * FOODIIE — site header partial with mega menu & mobile drawer.
 * Vars: $site, $mega_menu, $nav_categories
 */
$brand = $site['name'] ?? 'FOODIIE';
$mega = $mega_menu ?? [];
$cuisines = $mega['cuisine'] ?? [];
$courses = $mega['course'] ?? [];
$diets = $mega['diet'] ?? [];
$ingredients = $mega['ingredient'] ?? [];
?>
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= esc(u('/')) ?>" aria-label="<?= esc($brand) ?> — home">
      <img class="brand-logo" src="<?= esc(u('assets/icons/logo.svg')) ?>" alt="" width="36" height="36" decoding="async">
      <span class="brand-name"><?= esc($brand) ?></span>
    </a>

    <?php partial('mega-menu', ['mega_menu' => $mega, 'site' => $site]); ?>

    <div class="header-actions">
      <a class="icon-link" href="<?= esc(u('search/')) ?>" aria-label="Search Foodiie" data-search-trigger>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      </a>
      <a class="btn-nav-cta" href="<?= esc(u('recipes/')) ?>">Explore Recipes</a>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-drawer" aria-label="Open menu">
        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
      </button>
    </div>
  </div>
</header>

<div class="drawer-overlay" id="drawer-overlay" hidden></div>
<aside class="mobile-drawer" id="mobile-drawer" aria-label="Site menu" hidden>
  <div class="drawer-head">
    <span class="brand-name"><?= esc($brand) ?></span>
    <button class="drawer-close" type="button" aria-label="Close menu">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
  <a class="drawer-search" href="<?= esc(u('search/')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
    Search Recipes &amp; Stories
  </a>

  <nav class="drawer-nav-wrap" aria-label="Mobile navigation">
    <ul class="drawer-nav">
      <li><a class="drawer-link" href="<?= esc(u('/')) ?>">Home</a></li>

      <!-- Recipes Accordion -->
      <li class="drawer-item">
        <details class="drawer-accordion">
          <summary class="drawer-summary">Recipes <span class="arrow" aria-hidden="true">&#9662;</span></summary>
          <div class="drawer-sub">
            <a href="<?= esc(u('recipes/')) ?>" class="drawer-sub-all">All Recipes &rarr;</a>
            <?php if (!empty($ingredients)): ?>
              <div class="drawer-sub-title">By Ingredient</div>
              <?php foreach (array_slice($ingredients, 0, 8) as $ing): ?>
                <a href="<?= esc(u('recipes/ingredient/' . $ing['slug'] . '/')) ?>"><?= esc($ing['name']) ?></a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </details>
      </li>

      <!-- Cuisines Accordion -->
      <li class="drawer-item">
        <details class="drawer-accordion">
          <summary class="drawer-summary">Cuisines <span class="arrow" aria-hidden="true">&#9662;</span></summary>
          <div class="drawer-sub">
            <?php foreach (array_slice($cuisines, 0, 12) as $c): ?>
              <a href="<?= esc(u('cuisine/' . $c['slug'] . '/')) ?>"><?= esc($c['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </details>
      </li>

      <!-- Courses Accordion -->
      <li class="drawer-item">
        <details class="drawer-accordion">
          <summary class="drawer-summary">Courses <span class="arrow" aria-hidden="true">&#9662;</span></summary>
          <div class="drawer-sub">
            <?php foreach (array_slice($courses, 0, 10) as $crs): ?>
              <a href="<?= esc(u('course/' . $crs['slug'] . '/')) ?>"><?= esc($crs['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </details>
      </li>

      <!-- Health Accordion -->
      <li class="drawer-item">
        <details class="drawer-accordion">
          <summary class="drawer-summary">Health &amp; Diets <span class="arrow" aria-hidden="true">&#9662;</span></summary>
          <div class="drawer-sub">
            <?php foreach (array_slice($diets, 0, 8) as $d): ?>
              <a href="<?= esc(u('diet/' . $d['slug'] . '/')) ?>"><?= esc($d['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </details>
      </li>

      <!-- Features Accordion -->
      <li class="drawer-item">
        <details class="drawer-accordion">
          <summary class="drawer-summary">Features <span class="arrow" aria-hidden="true">&#9662;</span></summary>
          <div class="drawer-sub">
            <a href="<?= esc(u('food-news/')) ?>">Food News</a>
            <a href="<?= esc(u('kitchen-hacks/')) ?>">Kitchen Hacks</a>
            <a href="<?= esc(u('health/')) ?>">Health &amp; Wellness</a>
            <a href="<?= esc(u('food-facts/')) ?>">Food Facts</a>
            <a href="<?= esc(u('food-tips/')) ?>">Cooking Tips</a>
            <a href="<?= esc(u('trending/')) ?>">Trending Stories</a>
          </div>
        </details>
      </li>

      <li><a class="drawer-link" href="<?= esc(u('videos/')) ?>">Videos</a></li>
      <li>
        <a class="drawer-link" href="<?= esc(u('shorts/')) ?>" style="display:flex;align-items:center;justify-content:space-between;">
          <span>Shorts</span>
          <span class="nav-badge-short">⚡ New</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>
