<?php
/**
 * FOODIIE — interactive checkbox ingredients list partial.
 * Vars: $ingredients (array of strings), $servings (string)
 */
$ingredients = (array) ($ingredients ?? []);
$servings = trim((string) ($servings ?? ''));
if (empty($ingredients)) {
    return;
}
?>
<section class="recipe-section recipe-ingredients-section" id="ingredients-section" aria-labelledby="ingredients-title">
  <div class="recipe-section-head">
    <div class="recipe-section-title-wrap">
      <h2 id="ingredients-title" class="recipe-section-title">Ingredients</h2>
      <span class="recipe-section-badge"><?= count($ingredients) ?> items</span>
    </div>
    <div class="recipe-ing-actions" aria-label="Ingredient checklist actions">
      <button type="button" class="btn-ing-action" data-ing-action="check-all" title="Mark all ingredients as gathered">Check All</button>
      <span class="ing-action-sep" aria-hidden="true">&bull;</span>
      <button type="button" class="btn-ing-action" data-ing-action="uncheck-all" title="Reset all checkboxes">Reset</button>
    </div>
  </div>

  <?php if ($servings !== ''): ?>
    <div class="recipe-servings-note">
      <svg class="servings-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M18 8h1a4 4 0 0 1 0 8h-1"></path>
        <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>
        <line x1="6" y1="1" x2="6" y2="4"></line>
        <line x1="10" y1="1" x2="10" y2="4"></line>
        <line x1="14" y1="1" x2="14" y2="4"></line>
      </svg>
      <span>Yields: <strong><?= esc($servings) ?></strong></span>
      <span class="servings-subtext muted">(Tap items to check them off as you cook)</span>
    </div>
  <?php endif; ?>

  <ul class="recipe-ingredients-list" id="recipe-ingredients-list">
    <?php foreach ($ingredients as $idx => $ing): ?>
      <li class="recipe-ingredient-row">
        <label class="recipe-ingredient-label">
          <input type="checkbox" class="recipe-ingredient-cb" id="ing-item-<?= (int)$idx ?>">
          <span class="recipe-ingredient-box" aria-hidden="true">
            <svg class="recipe-check-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 8.5 6.5 12 13 4"></polyline>
            </svg>
          </span>
          <span class="recipe-ingredient-text"><?= esc($ing) ?></span>
        </label>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
