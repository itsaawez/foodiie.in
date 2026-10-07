<?php
/**
 * FOODIIE — Advanced client-side search & filter page.
 * Vars: $site, $page.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);
?>
<main id="main-content">
  <div class="container page-wrap" style="max-width:980px;padding-top:1.5rem;padding-bottom:3.5rem;">
    <!-- Search / Bookmarks Mode Tabs -->
    <div class="search-tabs" role="tablist">
      <button type="button" class="search-tab active" id="tab-search" data-tab="search" role="tab" aria-selected="true">
        🔍 Search Content
      </button>
      <button type="button" class="search-tab" id="tab-bookmarks" data-tab="bookmarks" role="tab" aria-selected="false">
        🔖 Saved Recipes (<span id="saved-recipes-count">0</span>)
      </button>
    </div>

    <!-- Search Section -->
    <div id="search-view">
      <header class="article-header" style="margin-bottom:1rem;">
        <h1 style="font-size:2rem;margin-bottom:.35rem;">Search Recipes &amp; Stories</h1>
        <p class="muted" style="margin:0;">Filter across recipes, ingredients, health stories, videos, and food shorts.</p>
      </header>

      <div class="search-controls">
        <form class="search-form" id="search-form" role="search">
          <div class="search-input-wrap">
            <input type="search" id="search-input" name="q" placeholder="Type to search (e.g. paneer, pasta, breakfast, keto, easy)..." autocomplete="off" aria-label="Search input">
            <button type="submit">Search</button>
          </div>
        </form>

        <!-- Filter Pills Bar -->
        <div class="filter-groups" id="search-filter-groups">
          <!-- Type Filter -->
          <div class="filter-row">
            <span class="filter-label">Type:</span>
            <div class="filter-pills" data-filter-group="type">
              <button type="button" class="filter-pill active" data-filter-val="">All</button>
              <button type="button" class="filter-pill" data-filter-val="recipe">🍽️ Recipes</button>
              <button type="button" class="filter-pill" data-filter-val="article">📰 Articles</button>
              <button type="button" class="filter-pill" data-filter-val="short">⚡ Shorts</button>
              <button type="button" class="filter-pill" data-filter-val="video">▶️ Videos</button>
            </div>
          </div>

          <!-- Cuisine Filter -->
          <div class="filter-row">
            <span class="filter-label">Cuisine:</span>
            <div class="filter-pills" data-filter-group="cuisine">
              <button type="button" class="filter-pill active" data-filter-val="">All Cuisines</button>
              <button type="button" class="filter-pill" data-filter-val="north indian">North Indian</button>
              <button type="button" class="filter-pill" data-filter-val="south indian">South Indian</button>
              <button type="button" class="filter-pill" data-filter-val="chinese">Chinese</button>
              <button type="button" class="filter-pill" data-filter-val="italian">Italian</button>
              <button type="button" class="filter-pill" data-filter-val="punjabi">Punjabi</button>
            </div>
          </div>

          <!-- Diet Filter -->
          <div class="filter-row">
            <span class="filter-label">Diet:</span>
            <div class="filter-pills" data-filter-group="diet">
              <button type="button" class="filter-pill active" data-filter-val="">All Diets</button>
              <button type="button" class="filter-pill" data-filter-val="veg">🌱 Vegetarian</button>
              <button type="button" class="filter-pill" data-filter-val="non-veg">🍗 Non-Veg</button>
              <button type="button" class="filter-pill" data-filter-val="vegan">🥑 Vegan</button>
            </div>
          </div>

          <!-- Course Filter -->
          <div class="filter-row">
            <span class="filter-label">Course:</span>
            <div class="filter-pills" data-filter-group="course">
              <button type="button" class="filter-pill active" data-filter-val="">All Courses</button>
              <button type="button" class="filter-pill" data-filter-val="breakfast">Breakfast</button>
              <button type="button" class="filter-pill" data-filter-val="lunch">Lunch</button>
              <button type="button" class="filter-pill" data-filter-val="dinner">Dinner</button>
              <button type="button" class="filter-pill" data-filter-val="snacks">Snacks</button>
            </div>
          </div>
        </div>
      </div>

      <div id="search-results" aria-live="polite">
        <p class="muted">Type keywords or click any filter pill above to browse items.</p>
      </div>

      <noscript>
        <p>Search needs JavaScript to work. In the meantime, browse our <a href="<?= esc(u('recipes/')) ?>">recipes</a>, <a href="<?= esc(u('food-facts/')) ?>">food facts</a> and <a href="<?= esc(u('videos/')) ?>">videos</a> directly.</p>
      </noscript>
    </div>

    <!-- Bookmarks / Saved View -->
    <div id="bookmarks-view" style="display:none;">
      <header class="article-header" style="margin-bottom:1.5rem;">
        <h1 style="font-size:2rem;margin-bottom:.35rem;">Saved Recipes</h1>
        <p class="muted" style="margin:0;">Recipes bookmarked in your browser for quick cooking reference.</p>
      </header>

      <div id="bookmarks-results" aria-live="polite"></div>
    </div>
  </div>
</main>
<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
