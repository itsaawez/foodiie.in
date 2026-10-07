<?php
/**
 * FOODIIE admin — Appearance & Homepage Settings.
 * Allows customization of Hero, Section Headings, Toggles, and Floating Action CTA.
 */
require_once __DIR__ . '/includes/guard.php';
require_once __DIR__ . '/includes/layout.php';

$fields = [
    // Hero
    'homepage_hero_eyebrow' => ['Hero Eyebrow Text', 'text', 'Default: FOOD • RECIPES • DISCOVERIES', 'FOOD • RECIPES • DISCOVERIES'],
    'homepage_hero_headline' => ['Hero Headline', 'text', 'Default: Good food starts with a good idea.', 'Good food starts with a good idea.'],
    'homepage_hero_subtitle' => ['Hero Subtitle', 'textarea', 'Supporting tagline under headline', 'Discover delicious recipes, food facts, healthy choices and ideas worth trying.'],
    'homepage_search_placeholder' => ['Search Input Placeholder', 'text', '', 'Search recipes, foods & ideas...'],
    'homepage_hero_cta_text' => ['Hero Button CTA Text', 'text', '', 'Explore Food'],
    'homepage_hero_cta_url' => ['Hero Button URL', 'text', 'Relative path, e.g. recipes/', 'recipes/'],

    // Section Titles
    'homepage_featured_title' => ["Featured Section Title", 'text', '', "What's cooking?"],
    'homepage_featured_subtitle' => ["Featured Section Subtitle", 'text', '', "Fresh ideas, kitchen-tested recipes and food discoveries."],
    'homepage_recipes_title' => ["Recipes Section Title", 'text', '', "Recipes worth saving"],
    'homepage_recipes_subtitle' => ["Recipes Section Subtitle", 'text', '', "Simple ideas for when you want something delicious."],
    'homepage_healthy_title' => ["Healthy Section Title", 'text', '', "Eat better, without making it boring."],
    'homepage_healthy_subtitle' => ["Healthy Section Subtitle", 'text', '', "Healthy breakfasts, high-protein foods, and smart nutrition habits."],
    'homepage_facts_title' => ["Food Facts Section Title", 'text', '', "Wait… did you know?"],
    'homepage_facts_subtitle' => ["Food Facts Section Subtitle", 'text', '', "Mind-blowing kitchen science, culinary history and food curiosities."],
    'homepage_videos_title' => ["Videos Section Title", 'text', '', "Watch. Learn. Cook."],
    'homepage_videos_subtitle' => ["Videos Section Subtitle", 'text', '', "Step-by-step video tutorials and chef masterclasses."],
    'homepage_trending_title' => ["Trending Stories Title", 'text', '', "Food everyone is talking about"],

    // Toggles
    'homepage_show_featured' => ['Enable "What\'s Cooking" Grid', 'checkbox', '', '1'],
    'homepage_show_recipes' => ['Enable "Recipes Worth Saving" Grid', 'checkbox', '', '1'],
    'homepage_show_healthy' => ['Enable Healthy Food Section', 'checkbox', '', '1'],
    'homepage_show_facts' => ['Enable Food Facts Section', 'checkbox', '', '1'],
    'homepage_show_shorts' => ['Enable Food Shorts Shelf', 'checkbox', '', '1'],
    'homepage_show_videos' => ['Enable Video Guides Section', 'checkbox', '', '1'],
    'homepage_show_cuisines' => ['Enable Cuisine Explorer Grid', 'checkbox', '', '1'],
    'homepage_show_trending' => ['Enable Trending Stories Section', 'checkbox', '', '1'],
    'homepage_show_floating_cta' => ['Enable Floating "What\'s cooking?" Action Pill', 'checkbox', '', '1'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $posted = (isset($_POST['s']) && is_array($_POST['s'])) ? $_POST['s'] : [];
    
    foreach ($fields as $key => [$label, $type, $hint, $default]) {
        if ($type === 'checkbox') {
            save_setting($key, !empty($posted[$key]) ? '1' : '0');
        } else {
            save_setting($key, trim((string) ($posted[$key] ?? '')));
        }
    }

    [$ok, $msg] = regen_site();
    flash($ok ? 'success' : 'warning', 'Homepage settings saved & site rebuilt. ' . $msg);
    redirect('homepage-settings.php');
}

admin_head('Homepage Settings', 'homepage-settings', $current_user);
?>

<form method="post" action="homepage-settings.php">
  <?php echo csrf_field(); ?>

  <!-- Hero Customization Panel -->
  <div class="panel">
    <h2>Hero Section (Yellow #FFE000 Startup Hero)</h2>
    <p class="hint">Customize the primary Clip Recipe-inspired discovery hero banner at the top of the homepage.</p>

    <div class="field">
      <label for="s-homepage_hero_eyebrow">Hero Eyebrow</label>
      <input type="text" id="s-homepage_hero_eyebrow" name="s[homepage_hero_eyebrow]" value="<?php echo esc(setting('homepage_hero_eyebrow', 'FOOD • RECIPES • DISCOVERIES')); ?>">
      <p class="hint">Displays in the rounded top pill badge.</p>
    </div>

    <div class="field">
      <label for="s-homepage_hero_headline">Main Headline</label>
      <input type="text" id="s-homepage_hero_headline" name="s[homepage_hero_headline]" value="<?php echo esc(setting('homepage_hero_headline', 'Good food starts with a good idea.')); ?>" required>
      <p class="hint">Desktop ~72px bold Montserrat headline.</p>
    </div>

    <div class="field">
      <label for="s-homepage_hero_subtitle">Supporting Subtitle</label>
      <textarea id="s-homepage_hero_subtitle" name="s[homepage_hero_subtitle]" rows="2"><?php echo esc(setting('homepage_hero_subtitle', 'Discover delicious recipes, food facts, healthy choices and ideas worth trying.')); ?></textarea>
    </div>

    <div class="field-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
      <div class="field">
        <label for="s-homepage_search_placeholder">Search Input Placeholder</label>
        <input type="text" id="s-homepage_search_placeholder" name="s[homepage_search_placeholder]" value="<?php echo esc(setting('homepage_search_placeholder', 'Search recipes, foods & ideas...')); ?>">
      </div>
      <div class="field">
        <label for="s-homepage_hero_cta_text">Button CTA Text</label>
        <input type="text" id="s-homepage_hero_cta_text" name="s[homepage_hero_cta_text]" value="<?php echo esc(setting('homepage_hero_cta_text', 'Explore Food')); ?>">
      </div>
      <div class="field">
        <label for="s-homepage_hero_cta_url">Button CTA URL</label>
        <input type="text" id="s-homepage_hero_cta_url" name="s[homepage_hero_cta_url]" value="<?php echo esc(setting('homepage_hero_cta_url', 'recipes/')); ?>">
      </div>
    </div>
  </div>

  <!-- Section Enable/Disable Toggles Panel -->
  <div class="panel">
    <h2>Homepage Sections &amp; Toggles</h2>
    <p class="hint">Toggle sections on or off to control what appears on the public homepage.</p>

    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:1rem;margin-top:1rem;">
      <?php
      $toggles = [
          'homepage_show_featured'     => 'What\'s Cooking (Asymmetric Editorial Grid)',
          'homepage_show_recipes'      => 'Recipes Worth Saving Section',
          'homepage_show_healthy'      => 'Healthy Food Section (#F1EEE5 Cream)',
          'homepage_show_facts'        => 'Food Facts Section (#FFF7C2 Pale Yellow)',
          'homepage_show_shorts'       => 'Food Shorts 60-Second Shelf',
          'homepage_show_videos'       => 'Video Guides ("Watch. Learn. Cook.")',
          'homepage_show_cuisines'     => 'Regional Cuisine Explorer Grid',
          'homepage_show_trending'     => 'Trending Stories Section',
          'homepage_show_floating_cta' => 'Floating Bottom-Right "What\'s Cooking?" Pill',
      ];
      foreach ($toggles as $tKey => $tLabel):
          $val = setting($tKey, '1');
      ?>
        <label style="display:flex;align-items:center;gap:.6rem;background:#F9FAFB;padding:.75rem 1rem;border-radius:8px;border:1px solid #E5E7EB;cursor:pointer;">
          <input type="checkbox" name="s[<?php echo esc($tKey); ?>]" value="1" <?php echo $val === '1' ? 'checked' : ''; ?>>
          <span style="font-weight:600;font-size:.9rem;"><?php echo esc($tLabel); ?></span>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Section Headings & Subtitles Customization -->
  <div class="panel">
    <h2>Section Headings &amp; Descriptions</h2>
    <p class="hint">Customize the editorial titles and subtitles displayed above each section.</p>

    <div class="field-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
      <div class="field">
        <label for="s-homepage_featured_title">Featured Section Title</label>
        <input type="text" id="s-homepage_featured_title" name="s[homepage_featured_title]" value="<?php echo esc(setting('homepage_featured_title', "What's cooking?")); ?>">
      </div>
      <div class="field">
        <label for="s-homepage_featured_subtitle">Featured Section Subtitle</label>
        <input type="text" id="s-homepage_featured_subtitle" name="s[homepage_featured_subtitle]" value="<?php echo esc(setting('homepage_featured_subtitle', "Fresh ideas, kitchen-tested recipes and food discoveries.")); ?>">
      </div>

      <div class="field">
        <label for="s-homepage_recipes_title">Recipes Section Title</label>
        <input type="text" id="s-homepage_recipes_title" name="s[homepage_recipes_title]" value="<?php echo esc(setting('homepage_recipes_title', 'Recipes worth saving')); ?>">
      </div>
      <div class="field">
        <label for="s-homepage_recipes_subtitle">Recipes Section Subtitle</label>
        <input type="text" id="s-homepage_recipes_subtitle" name="s[homepage_recipes_subtitle]" value="<?php echo esc(setting('homepage_recipes_subtitle', 'Simple ideas for when you want something delicious.')); ?>">
      </div>

      <div class="field">
        <label for="s-homepage_healthy_title">Healthy Section Title</label>
        <input type="text" id="s-homepage_healthy_title" name="s[homepage_healthy_title]" value="<?php echo esc(setting('homepage_healthy_title', 'Eat better, without making it boring.')); ?>">
      </div>
      <div class="field">
        <label for="s-homepage_healthy_subtitle">Healthy Section Subtitle</label>
        <input type="text" id="s-homepage_healthy_subtitle" name="s[homepage_healthy_subtitle]" value="<?php echo esc(setting('homepage_healthy_subtitle', 'Healthy breakfasts, high-protein foods, and smart nutrition habits.')); ?>">
      </div>

      <div class="field">
        <label for="s-homepage_facts_title">Food Facts Section Title</label>
        <input type="text" id="s-homepage_facts_title" name="s[homepage_facts_title]" value="<?php echo esc(setting('homepage_facts_title', 'Wait… did you know?')); ?>">
      </div>
      <div class="field">
        <label for="s-homepage_facts_subtitle">Food Facts Section Subtitle</label>
        <input type="text" id="s-homepage_facts_subtitle" name="s[homepage_facts_subtitle]" value="<?php echo esc(setting('homepage_facts_subtitle', 'Mind-blowing kitchen science, culinary history and food curiosities.')); ?>">
      </div>

      <div class="field">
        <label for="s-homepage_videos_title">Videos Section Title</label>
        <input type="text" id="s-homepage_videos_title" name="s[homepage_videos_title]" value="<?php echo esc(setting('homepage_videos_title', 'Watch. Learn. Cook.')); ?>">
      </div>
      <div class="field">
        <label for="s-homepage_videos_subtitle">Videos Section Subtitle</label>
        <input type="text" id="s-homepage_videos_subtitle" name="s[homepage_videos_subtitle]" value="<?php echo esc(setting('homepage_videos_subtitle', 'Step-by-step video tutorials and chef masterclasses.')); ?>">
      </div>
    </div>
  </div>

  <div class="actions">
    <button type="submit" class="btn btn-primary" style="background:#FFE000;color:#202421;font-weight:800;padding:.8rem 2rem;border-radius:10px;border:0;cursor:pointer;">
      💾 Save Changes &amp; Rebuild Homepage
    </button>
  </div>
</form>

<?php admin_foot(); ?>
