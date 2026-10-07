<?php
/**
 * FOODIIE — 404 template.
 * Vars: $site,$page.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);
?>
<main id="main-content">
  <div class="container page-404">
    <h1>Oops! This recipe seems to have disappeared from the kitchen.</h1>
    <p>The page you're looking for doesn't exist or may have been moved. Let's get you back to something delicious.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="<?= esc(u('/')) ?>">Go Home</a>
      <a class="btn btn-outline" href="<?= esc(u('recipes/')) ?>">Explore Recipes</a>
      <a class="btn btn-outline" href="<?= esc(u('search/')) ?>">Search Foodiie</a>
    </div>
  </div>
</main>
<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
