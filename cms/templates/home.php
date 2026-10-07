<?php
/**
 * FOODIIE — Modern Food Discovery Platform Homepage Template
 * Inspired by modern startup / SaaS food media design (Clip Recipe inspiration)
 * 
 * Vars: $site, $page, $jsonld, $trending, $latest_recipes, $featured_recipe,
 *       $healthy_items, $fact_items, $videos, $shorts,
 *       $popular_ingredients, $popular_cuisines, $nav_categories.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => $jsonld ?? [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);

// CMS Settings & Customizations
$hero_eyebrow = setting('homepage_hero_eyebrow', 'FOOD • RECIPES • DISCOVERIES');
$hero_headline = setting('homepage_hero_headline', 'Good food starts with a good idea.');
$hero_subtitle = setting('homepage_hero_subtitle', 'Discover delicious recipes, food facts, healthy choices and ideas worth trying.');
$hero_cta_text = setting('homepage_hero_cta_text', 'Explore Food');
$hero_cta_url  = setting('homepage_hero_cta_url', 'recipes/');
$search_placeholder = setting('homepage_search_placeholder', 'Search recipes, foods & ideas...');

// Section toggles
$show_featured   = setting('homepage_show_featured', '1') === '1';
$show_recipes    = setting('homepage_show_recipes', '1') === '1';
$show_healthy    = setting('homepage_show_healthy', '1') === '1';
$show_facts      = setting('homepage_show_facts', '1') === '1';
$show_videos     = setting('homepage_show_videos', '1') === '1';
$show_shorts     = setting('homepage_show_shorts', '1') === '1';
$show_cuisines   = setting('homepage_show_cuisines', '1') === '1';
$show_trending   = setting('homepage_show_trending', '1') === '1';
$show_floating   = setting('homepage_show_floating_cta', '1') === '1';

// Asymmetric grid items
$lead_item = $featured_recipe ?? (!empty($latest_recipes) ? $latest_recipes[0] : null);
$sub_items = !empty($trending) ? array_slice($trending, 0, 2) : [];

// Curated interactive food facts if database items are fewer than 3
$curated_facts = [
    [
        'badge' => 'Food Science',
        'question' => 'Why does cutting onions make you cry?',
        'answer' => 'Cutting onions breaks cell walls, releasing alliinase and sulfoxides that form syn-propanethial-S-oxide gas. When it reaches your eyes, your lachrymal glands trigger tears to flush it out.',
        'link' => u('food-facts/why-cutting-onions-makes-you-cry/'),
    ],
    [
        'badge' => 'Culinary Hack',
        'question' => 'Why does bread go stale even in a plastic bag?',
        'answer' => 'Staling is not just water loss; it is starch retrogradation. Gelatinized amylopectin molecules recrystallize over time, recrystallizing water molecules back into rigid structures.',
        'link' => u('food-facts/science-behind-stale-bread/'),
    ],
    [
        'badge' => 'Taste Chemistry',
        'question' => 'Why does spicy food feel hot when it has no heat?',
        'answer' => 'Capsaicin binds directly to TRPV1 nerve receptors in your mouth—the exact same sensors that detect boiling temperatures (>43°C), fooling your brain into feeling fire.',
        'link' => u('food-facts/why-spicy-food-burns/'),
    ],
];
?>
<main id="main-content">

  <!-- ==================== HERO SECTION ==================== -->
  <section class="foodie-hero" aria-label="Hero Introduction">
    <!-- Floating Food Collage Elements -->
    <div class="foodie-floating-elements" aria-hidden="true">
      <img src="<?= esc(u('assets/images/food/lemon.svg')) ?>" class="foodie-float-item float-lemon" alt="" width="110" height="110" loading="eager" decoding="async">
      <img src="<?= esc(u('assets/images/food/avocado.svg')) ?>" class="foodie-float-item float-avocado" alt="" width="130" height="130" loading="eager" decoding="async">
      <img src="<?= esc(u('assets/images/food/tomato.svg')) ?>" class="foodie-float-item float-tomato" alt="" width="115" height="115" loading="eager" decoding="async">
      <img src="<?= esc(u('assets/images/food/chili.svg')) ?>" class="foodie-float-item float-chili" alt="" width="110" height="110" loading="eager" decoding="async">
      <img src="<?= esc(u('assets/images/food/strawberry.svg')) ?>" class="foodie-float-item float-strawberry" alt="" width="85" height="85" loading="eager" decoding="async">
      <img src="<?= esc(u('assets/images/food/basil.svg')) ?>" class="foodie-float-item float-basil" alt="" width="95" height="95" loading="eager" decoding="async">
      <img src="<?= esc(u('assets/images/food/bowl.svg')) ?>" class="foodie-float-item float-bowl" alt="" width="160" height="160" loading="eager" decoding="async">
    </div>

    <div class="hero-center-content">
      <div class="hero-eyebrow">
        <span>✨</span> <?= esc($hero_eyebrow) ?>
      </div>

      <h1 class="hero-headline">
        <?= esc($hero_headline) ?>
      </h1>

      <p class="hero-subtitle">
        <?= esc($hero_subtitle) ?>
      </p>

      <!-- Large Central Search Box -->
      <form class="foodie-hero-search" action="<?= esc(u('search/')) ?>" method="get" role="search">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" placeholder="<?= esc($search_placeholder) ?>" aria-label="Search recipes, ingredients and food ideas" autocomplete="off">
        <button type="submit">
          <span><?= esc($hero_cta_text) ?></span>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </form>

      <!-- Discovery Pills -->
      <nav class="hero-pills" aria-label="Quick Discovery Pills">
        <a href="<?= esc(u('recipes/')) ?>" class="hero-pill">🍳 Recipes</a>
        <a href="<?= esc(u('health/')) ?>" class="hero-pill">🥗 Healthy</a>
        <a href="<?= esc(u('food-facts/')) ?>" class="hero-pill">💡 Food Facts</a>
        <a href="<?= esc(u('course/breakfast/')) ?>" class="hero-pill">⚡ Quick Meals</a>
        <a href="<?= esc(u('cuisine/north-indian/')) ?>" class="hero-pill">🍛 Indian Food</a>
        <a href="<?= esc(u('dessert/')) ?>" class="hero-pill">🍰 Desserts</a>
      </nav>
    </div>
  </section>

  <!-- ==================== TRUST / DISCOVERY STRIP ==================== -->
  <section class="discovery-strip" aria-label="Explore food from every angle">
    <div class="container discovery-strip-inner">
      <p class="discovery-strip-label">
        <span>⚡</span> Explore food from every angle:
      </p>
      <div class="discovery-strip-categories">
        <a href="<?= esc(u('recipes/')) ?>" class="discovery-chip">🍳 Recipes</a>
        <a href="<?= esc(u('health/')) ?>" class="discovery-chip">🥗 Healthy</a>
        <a href="<?= esc(u('food-facts/')) ?>" class="discovery-chip">💡 Food Facts</a>
        <a href="<?= esc(u('trending/')) ?>" class="discovery-chip">🔥 Trends</a>
        <a href="<?= esc(u('videos/')) ?>" class="discovery-chip">🎥 Videos</a>
        <a href="<?= esc(u('shorts/')) ?>" class="discovery-chip">⚡ Shorts</a>
      </div>
    </div>
  </section>

  <?= ad_html('header') ?>

  <!-- ==================== FEATURED ASYMMETRIC GRID ("What's cooking?") ==================== -->
  <?php if ($show_featured && $lead_item): ?>
    <section class="section" style="padding-top:3.5rem;padding-bottom:3rem;" aria-labelledby="featured-section-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="featured-section-title"><?= esc(setting('homepage_featured_title', "What's cooking?")) ?></h2>
            <p><?= esc(setting('homepage_featured_subtitle', 'Fresh ideas, kitchen-tested recipes and food discoveries.')) ?></p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('recipes/')) ?>">Browse all &rarr;</a>
          </div>
        </div>

        <div class="editorial-grid">
          <!-- Lead 60% Card -->
          <article class="editorial-lead-card">
            <?php
              $lead_url = !empty($lead_item['url']) ? $lead_item['url'] : u('recipes/' . ($lead_item['slug'] ?? '') . '/');
              $lead_raw_img = (string) ($lead_item['image'] ?? '');
              if ($lead_raw_img === '') {
                  $lead_img = u('assets/images/hero.jpg');
              } elseif (strpos($lead_raw_img, '/') === 0 || preg_match('#^https?://#i', $lead_raw_img)) {
                  $lead_img = $lead_raw_img;
              } else {
                  $lead_img = public_image_url($lead_raw_img);
              }
              $lead_cat = $lead_item['category'] ?? ($lead_item['cuisine'] ?? 'Featured Recipe');
              $lead_name = $lead_item['name'] ?? ($lead_item['title'] ?? '');
              $lead_desc = $lead_item['description'] ?? '';
            ?>
            <a href="<?= esc($lead_url) ?>" class="editorial-lead-media" tabindex="-1" aria-hidden="true">
              <img src="<?= esc($lead_img) ?>" alt="<?= esc($lead_name) ?>" fetchpriority="high" decoding="async" width="700" height="420">
            </a>
            <div class="editorial-lead-body">
              <span class="card-tag"><?= esc($lead_cat) ?></span>
              <h3><a href="<?= esc($lead_url) ?>"><?= esc($lead_name) ?></a></h3>
              <?php if (!empty($lead_desc)): ?>
                <p><?= esc(excerpt($lead_desc, 130)) ?></p>
              <?php endif; ?>
              <div style="margin-top:auto;display:flex;align-items:center;justify-content:space-between;padding-top:1rem;">
                <span class="muted" style="font-size:.88rem;font-weight:600;">
                  ⏱️ <?= esc($lead_item['total_time'] ?? '25 min') ?> &bull; ⚡ <?= esc(ucfirst($lead_item['difficulty'] ?? 'Easy')) ?>
                </span>
                <a href="<?= esc($lead_url) ?>" class="btn btn-dark btn-sm">Cook This &rarr;</a>
              </div>
            </div>
          </article>

          <!-- 40% Two Stacked Cards -->
          <div class="editorial-stack">
            <?php foreach ($sub_items as $sub): ?>
              <?php
                $s_url = $sub['url'] ?? u('articles/' . ($sub['slug'] ?? '') . '/');
                $s_raw_img = (string) ($sub['image'] ?? '');
                if ($s_raw_img === '') {
                    $s_img = u('assets/images/placeholder.jpg');
                } elseif (strpos($s_raw_img, '/') === 0 || preg_match('#^https?://#i', $s_raw_img)) {
                    $s_img = $s_raw_img;
                } else {
                    $s_img = public_image_url($s_raw_img);
                }
              ?>
              <article class="editorial-sub-card">
                <a href="<?= esc($s_url) ?>" class="editorial-sub-media" tabindex="-1" aria-hidden="true">
                  <img src="<?= esc($s_img) ?>" alt="<?= esc($sub['title'] ?? '') ?>" loading="lazy" decoding="async" width="280" height="180">
                </a>
                <div class="editorial-sub-body">
                  <span class="card-tag" style="margin-bottom:.2rem;"><?= esc($sub['category'] ?? 'Trending') ?></span>
                  <h4><a href="<?= esc($s_url) ?>"><?= esc($sub['title'] ?? '') ?></a></h4>
                  <p><?= esc(excerpt($sub['description'] ?? '', 85)) ?></p>
                  <a href="<?= esc($s_url) ?>" style="font-size:.84rem;font-weight:750;color:var(--dark);margin-top:.4rem;text-decoration:none;">Read story &rarr;</a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ==================== FEATURED RECIPE SECTION ("Recipes worth saving") ==================== -->
  <?php if ($show_recipes && !empty($latest_recipes)): ?>
    <section class="section" style="padding:4rem 0;" aria-labelledby="recipes-worth-saving-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="recipes-worth-saving-title"><?= esc(setting('homepage_recipes_title', 'Recipes worth saving')) ?></h2>
            <p><?= esc(setting('homepage_recipes_subtitle', 'Simple ideas for when you want something delicious.')) ?></p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('recipes/')) ?>">View all recipes &rarr;</a>
          </div>
        </div>

        <div class="card-grid">
          <?php foreach (array_slice($latest_recipes, 0, 6) as $item): ?>
            <?php partial('card', ['item' => $item]); ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ==================== HEALTHY FOOD SECTION ==================== -->
  <?php if ($show_healthy && !empty($healthy_items)): ?>
    <section class="section-healthy" aria-labelledby="healthy-section-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="healthy-section-title"><?= esc(setting('homepage_healthy_title', 'Eat better, without making it boring.')) ?></h2>
            <p><?= esc(setting('homepage_healthy_subtitle', 'Healthy breakfasts, high-protein foods, and smart nutrition habits.')) ?></p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('health/')) ?>">Explore health articles &rarr;</a>
          </div>
        </div>

        <div class="card-grid">
          <?php foreach (array_slice($healthy_items, 0, 3) as $item): ?>
            <?php partial('card', ['item' => $item]); ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ==================== FOOD FACTS SECTION ("Wait… did you know?") ==================== -->
  <?php if ($show_facts): ?>
    <section class="section-facts" aria-labelledby="food-facts-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="food-facts-title"><?= esc(setting('homepage_facts_title', 'Wait… did you know?')) ?></h2>
            <p><?= esc(setting('homepage_facts_subtitle', 'Mind-blowing kitchen science, culinary history and food curiosities.')) ?></p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('food-facts/')) ?>">All food facts &rarr;</a>
          </div>
        </div>

        <div class="facts-grid">
          <?php foreach ($curated_facts as $f): ?>
            <article class="fact-card">
              <div>
                <span class="fact-card-badge"><?= esc($f['badge']) ?></span>
                <h3 class="fact-card-question"><?= esc($f['question']) ?></h3>
                <p class="fact-card-answer"><?= esc($f['answer']) ?></p>
              </div>
              <a href="<?= esc($f['link']) ?>" class="fact-card-link">Learn the science &rarr;</a>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ==================== FOOD SHORTS SECTION (60s Bites) ==================== -->
  <?php if ($show_shorts && !empty($shorts)): ?>
    <section class="section" style="background:#FFFDF7;padding:3.5rem 0;" aria-labelledby="shorts-home-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="shorts-home-title" style="display:flex;align-items:center;gap:.65rem;">
              <span>Food Shorts</span>
              <span class="nav-badge-short">⚡ 60s Bites</span>
            </h2>
            <p>Quick kitchen hacks, viral recipes and 60-second cooking inspiration.</p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('shorts/')) ?>">Watch all shorts &rarr;</a>
          </div>
        </div>

        <div class="shorts-shelf">
          <?php foreach (array_slice($shorts, 0, 5) as $s): ?>
            <?php
              $vid = (string) ($s['video_id'] ?? '');
              $title = (string) ($s['title'] ?? '');
              $thumb = !empty($s['thumbnail']) ? $s['thumbnail'] : ($vid ? "https://img.youtube.com/vi/{$vid}/hqdefault.jpg" : '');
            ?>
            <div class="short-card" data-video-id="<?= esc($vid) ?>" data-title="<?= esc($title) ?>" role="button" tabindex="0" aria-label="Play <?= esc($title) ?>">
              <span class="short-badge">Short</span>
              <img src="<?= esc($thumb) ?>" alt="<?= esc($title) ?>" loading="lazy" decoding="async" width="220" height="390">
              <div class="short-play" aria-hidden="true">&#9658;</div>
              <div class="short-overlay">
                <h3 class="short-title"><?= esc($title) ?></h3>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ==================== VIDEO GUIDES ("Watch. Learn. Cook.") ==================== -->
  <?php if ($show_videos && !empty($videos)): ?>
    <section class="section" style="padding:4rem 0;" aria-labelledby="videos-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="videos-title"><?= esc(setting('homepage_videos_title', 'Watch. Learn. Cook.')) ?></h2>
            <p><?= esc(setting('homepage_videos_subtitle', 'Step-by-step video tutorials and chef masterclasses.')) ?></p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('videos/')) ?>">View all videos &rarr;</a>
          </div>
        </div>

        <div class="card-grid">
          <?php foreach (array_slice($videos, 0, 3) as $v):
              $vid = (string) ($v['video_id'] ?? '');
              $thumb = video_thumbnail_url($v);
              if ($thumb === '') $thumb = u('assets/images/placeholder.jpg'); ?>
            <div class="card" style="box-shadow:var(--shadow);">
              <div class="yt-facade" data-id="<?= esc($vid) ?>" role="button" tabindex="0" aria-label="Play video: <?= esc($v['title'] ?? '') ?>" style="margin-bottom:0;border-radius:0;">
                <img src="<?= esc($thumb) ?>" alt="<?= esc($v['title'] ?? '') ?>" loading="lazy" decoding="async" width="480" height="270">
                <span class="yt-play" aria-hidden="true"></span>
              </div>
              <div class="card-body">
                <h3 class="card-title" style="font-size:1.15rem;"><?= esc($v['title'] ?? '') ?></h3>
                <?php if (!empty($v['description'])): ?>
                  <p class="card-desc"><?= esc(excerpt($v['description'], 90)) ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ==================== CUISINE EXPLORER ==================== -->
  <?php if ($show_cuisines && !empty($popular_cuisines)): ?>
    <section class="section" style="background:#FFFBEB;padding:4rem 0;" aria-labelledby="cuisines-explore-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="cuisines-explore-title">Explore by Cuisine</h2>
            <p>Authentic flavors from Indian regional kitchens and global favorites.</p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('recipes/')) ?>">All Cuisines &rarr;</a>
          </div>
        </div>

        <div class="cuisine-grid">
          <?php foreach ($popular_cuisines as $c): ?>
            <?php $c_url = !empty($c['url']) ? u($c['url']) : u('cuisine/' . $c['slug'] . '/'); ?>
            <a href="<?= esc($c_url) ?>" class="cuisine-card">
              <div>
                <div class="cuisine-card-head">
                  <span style="font-size:1.6rem;"><?= esc($c['emoji']) ?></span>
                  <span class="cuisine-card-badge"><?= esc($c['badge']) ?></span>
                </div>
                <h3 class="cuisine-card-title"><?= esc($c['name']) ?></h3>
                <p class="cuisine-card-desc"><?= esc($c['desc']) ?></p>
              </div>
              <span class="cuisine-card-link">Explore recipes &rarr;</span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ==================== TRENDING STORIES ==================== -->
  <?php if ($show_trending && !empty($trending)): ?>
    <section class="section" style="padding:4rem 0;" aria-labelledby="trending-stories-title">
      <div class="container">
        <div class="section-head-startup">
          <div class="head-text">
            <h2 id="trending-stories-title"><?= esc(setting('homepage_trending_title', 'Food everyone is talking about')) ?></h2>
            <p>Trending food stories, viral recipes and delicious discoveries.</p>
          </div>
          <div class="head-action">
            <a href="<?= esc(u('articles/')) ?>">View all stories &rarr;</a>
          </div>
        </div>

        <div class="card-grid">
          <?php foreach (array_slice($trending, 0, 3) as $item): ?>
            <?php partial('card', ['item' => $item]); ?>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?= ad_html('homepage') ?>

  <!-- ==================== NEWSLETTER SIGNUP ==================== -->
  <section class="newsletter" aria-labelledby="newsletter-title">
    <div class="container newsletter-inner">
      <h2 id="newsletter-title">Get the freshest food inspiration</h2>
      <p>One tasty email every weekend. Handpicked recipes, seasonal menus and quick kitchen hacks — no spam, ever.</p>
      <form class="newsletter-form" data-newsletter action="<?= esc(u('api/subscribe.php')) ?>" method="post">
        <label class="sr-only" for="nl-email-home">Email address</label>
        <input type="email" id="nl-email-home" name="email" placeholder="you@example.com" required autocomplete="email">
        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
        <input type="hidden" name="source" value="homepage">
        <button class="btn btn-primary" type="submit">Subscribe</button>
        <p class="form-msg" aria-live="polite"></p>
      </form>
    </div>
  </section>

</main>

<!-- ==================== FLOATING ACTION BUTTON ==================== -->
<?php if ($show_floating): ?>
  <button type="button" class="foodie-floating-cta" id="floatingCtaBtn" aria-label="Find recipes and food ideas">
    <span>🍳</span>
    <span>What's cooking?</span>
  </button>
<?php endif; ?>

<!-- ==================== QUICK SEARCH MODAL ==================== -->
<div class="foodie-search-modal" id="searchModal" aria-hidden="true" role="dialog" aria-label="Search recipes and ideas">
  <div class="search-modal-box">
    <button type="button" class="search-modal-close" id="searchModalClose" aria-label="Close search">&times;</button>
    
    <h3 style="font-size:1.4rem;font-weight:900;margin-bottom:1.25rem;color:var(--dark);">What are you craving?</h3>
    
    <form action="<?= esc(u('search/')) ?>" method="get">
      <div class="search-modal-input-wrap">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" style="color:#9CA3AF;margin-right:.5rem;"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" id="searchModalInput" placeholder="Type a dish, ingredient, or diet..." autocomplete="off" aria-label="Search recipes and ideas">
        <button type="submit" class="btn btn-dark btn-sm" style="padding:.4rem 1rem;">Search</button>
      </div>
    </form>

    <div class="search-modal-suggestions">
      <div class="search-modal-suggestions-title">Popular Suggestions</div>
      <div class="search-modal-tags">
        <a href="<?= esc(u('search/?q=biryani')) ?>" class="search-modal-tag">🍛 Biryani</a>
        <a href="<?= esc(u('search/?q=paneer')) ?>" class="search-modal-tag">🧀 Paneer</a>
        <a href="<?= esc(u('search/?q=breakfast')) ?>" class="search-modal-tag">🥞 Breakfast</a>
        <a href="<?= esc(u('search/?q=high+protein')) ?>" class="search-modal-tag">💪 High Protein</a>
        <a href="<?= esc(u('search/?q=dessert')) ?>" class="search-modal-tag">🍰 Desserts</a>
        <a href="<?= esc(u('search/?q=quick+dinner')) ?>" class="search-modal-tag">⚡ Quick Dinner</a>
      </div>
    </div>
  </div>
</div>

<!-- ==================== SHORTS MODAL PLAYER ==================== -->
<div class="short-modal" id="shortModal" aria-hidden="true" role="dialog" aria-label="Video Player">
  <div class="short-modal-inner">
    <button type="button" class="short-modal-close" id="modalClose" aria-label="Close video">&times;</button>
    <iframe id="modalFrame" src="about:blank" style="width:100%;height:100%;border:0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
  </div>
</div>

<script>
(function() {
  // Shorts Modal Logic
  var modal = document.getElementById('shortModal');
  var frame = document.getElementById('modalFrame');
  var closeBtn = document.getElementById('modalClose');

  function openShort(vid) {
    if (!vid || !frame || !modal) return;
    frame.src = 'https://www.youtube.com/embed/' + encodeURIComponent(vid) + '?autoplay=1&rel=0';
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeShort() {
    if (!frame || !modal) return;
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

  // Quick Search Modal Logic
  var sModal = document.getElementById('searchModal');
  var sClose = document.getElementById('searchModalClose');
  var sInput = document.getElementById('searchModalInput');
  var sFloat = document.getElementById('floatingCtaBtn');

  function openSearchModal(e) {
    if (e) e.preventDefault();
    if (!sModal) return;
    sModal.classList.add('active');
    sModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (sInput) setTimeout(function() { sInput.focus(); }, 100);
  }

  function closeSearchModal() {
    if (!sModal) return;
    sModal.classList.remove('active');
    sModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  if (sFloat) sFloat.addEventListener('click', openSearchModal);
  if (sClose) sClose.addEventListener('click', closeSearchModal);

  document.querySelectorAll('[data-search-trigger]').forEach(function(btn) {
    btn.addEventListener('click', openSearchModal);
  });

  if (sModal) {
    sModal.addEventListener('click', function(e) {
      if (e.target === sModal) closeSearchModal();
    });
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      if (modal && modal.classList.contains('active')) closeShort();
      if (sModal && sModal.classList.contains('active')) closeSearchModal();
    }
  });
})();
</script>

<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
