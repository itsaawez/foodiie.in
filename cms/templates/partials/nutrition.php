<?php
/**
 * FOODIIE — rich nutrition card partial.
 * Vars: $recipe (array), $nutrition_rows (array of label => value)
 */
$nutrition_rows = (array) ($nutrition_rows ?? []);
$calories = trim((string) ($nutrition_rows['Calories'] ?? $recipe['calories'] ?? ''));
$protein = trim((string) ($nutrition_rows['Protein'] ?? $recipe['protein'] ?? ''));
$carbs = trim((string) ($nutrition_rows['Carbs'] ?? $recipe['carbs'] ?? ''));
$fat = trim((string) ($nutrition_rows['Fat'] ?? $recipe['fat'] ?? ''));
$servings = trim((string) ($recipe['servings'] ?? '1 serving'));

$has_nutrition = $calories !== '' || $protein !== '' || $carbs !== '' || $fat !== '';
if (!$has_nutrition) {
    return;
}
?>
<section class="recipe-section recipe-nutrition-card" id="nutrition-section" aria-labelledby="nutrition-title">
  <div class="nutrition-header">
    <div class="nutrition-icon-wrap">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
      </svg>
    </div>
    <div>
      <h2 id="nutrition-title" class="nutrition-heading">Nutrition Facts</h2>
      <p class="nutrition-subtitle muted">Estimated per serving (<?= esc($servings) ?>)</p>
    </div>
  </div>

  <div class="nutrition-body">
    <?php if ($calories !== ''): ?>
      <div class="nutrition-calories-block">
        <span class="nutrition-cal-number"><?= esc(preg_replace('/[^0-9.]/', '', $calories) ?: $calories) ?></span>
        <span class="nutrition-cal-unit">kcal / Calories</span>
      </div>
    <?php endif; ?>

    <div class="nutrition-grid">
      <?php if ($protein !== ''): ?>
        <div class="nutrition-item">
          <span class="nutrition-item-label">Protein</span>
          <span class="nutrition-item-value"><?= esc($protein) ?></span>
          <div class="nutrition-bar"><div class="nutrition-bar-fill protein-bar" style="width: 65%;"></div></div>
        </div>
      <?php endif; ?>

      <?php if ($carbs !== ''): ?>
        <div class="nutrition-item">
          <span class="nutrition-item-label">Carbs</span>
          <span class="nutrition-item-value"><?= esc($carbs) ?></span>
          <div class="nutrition-bar"><div class="nutrition-bar-fill carbs-bar" style="width: 50%;"></div></div>
        </div>
      <?php endif; ?>

      <?php if ($fat !== ''): ?>
        <div class="nutrition-item">
          <span class="nutrition-item-label">Total Fat</span>
          <span class="nutrition-item-value"><?= esc($fat) ?></span>
          <div class="nutrition-bar"><div class="nutrition-bar-fill fat-bar" style="width: 40%;"></div></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <p class="nutrition-disclaimer muted">
    * Nutritional values are approximate and intended for informational purposes only. Actual values may vary based on ingredient brands and portion sizes.
  </p>
</section>
